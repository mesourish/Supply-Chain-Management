<?php

use Livewire\Volt\Component;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // View Mode & State
    public $viewMode = 'table';
    public $showDetailsModal = false;
    public $selectedQuoteId = null;
    public $selectedQuote = null;
    
    // Unified Inline Workspace State (like PO/SO)
    public $isEditing = false;
    public $editQuoteId = null;

    // Unified Form fields
    public $customer_id = '';
    public $crm_lead_id = null;
    public $valid_until = '';
    public $shipping_amount = 0.00;
    public $notes = '';
    public $apply_gst = false;
    public $gst_type = 'exclusive';
    public $gst_percentage = 0;
    public $items = []; // array of ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
    public $showForm = false;
    
    public function mount()
    {
        if (!auth()->user()->can('view quotations')) { abort(403); }
        $this->gst_percentage = (float)setting('default_gst_percentage', '18');
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->resetForm();
    }

    public function getQuotesList()
    {
        return Quotation::with(['customer', 'items.product'])->latest()->get();
    }

    public function getCustomersList()
    {
        return Customer::orderBy('name')->get();
    }

    public function getProductsList()
    {
        return Product::orderBy('name')->get();
    }

    public function resetForm()
    {
        $this->customer_id = '';
        $this->crm_lead_id = null;
        $this->valid_until = now()->addDays(30)->toDateString();
        $this->shipping_amount = 0.00;
        $this->notes = '';
        $this->apply_gst = false;
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->gst_percentage = (float)setting('default_gst_percentage', '18');
        $this->items = [
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
        ];
        $this->isEditing = false;
        $this->editQuoteId = null;
        $this->showForm = false;
    }

    public function toggleForm()
    {
        if ($this->showForm && !$this->isEditing) {
            $this->showForm = false;
        } else {
            $this->resetForm();
            $this->showForm = true;
        }
    }

    public function addItemLine()
    {
        $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false];
    }

    public function addBlankLine()
    {
        $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true];
    }

    public function removeItemLine($index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function updated($key, $value)
    {
        if (str_contains($key, 'items.') && str_contains($key, '.product_id')) {
            $parts = explode('.', $key);
            $index = $parts[1] ?? null;
            
            if ($index !== null && isset($this->items[$index])) {
                $productId = $value;
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $this->items[$index]['unit_price'] = $product->unit_price;
                    }
                }
            }
        }
    }

    public function getSubtotalProperty()
    {
        $sub = 0;
        foreach($this->items as $item) {
            $sub += ((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0));
        }
        return $sub;
    }

    public function getCalculatedTaxProperty()
    {
        if (!$this->apply_gst) return 0;
        $sub = $this->subtotal;
        if ($this->gst_type === 'inclusive') {
            return $sub - ($sub / (1 + ($this->gst_percentage / 100)));
        }
        return $sub * ($this->gst_percentage / 100);
    }

    public function getTotalProperty()
    {
        $sub = $this->subtotal;
        if ($this->apply_gst && $this->gst_type === 'inclusive') {
            return $sub + (float)$this->shipping_amount;
        }
        return $sub + $this->calculatedTax + (float)$this->shipping_amount;
    }

    public function saveQuote()
    {
        if ($this->isEditing) {
            if (!auth()->user()->can('edit quotations')) { abort(403); }
            $this->validate([
                'customer_id' => 'required|exists:customers,id',
                'valid_until' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required_if:items.*.is_blank,false',
                'items.*.description' => 'required_if:items.*.is_blank,true',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
            ], [
                'items.*.product_id.required_if' => 'Please select a product for the line.',
                'items.*.description.required_if' => 'Please provide a description for the blank line.',
                'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            ]);

            DB::beginTransaction();
            try {
                $quote = Quotation::findOrFail($this->editQuoteId);
                $quote->items()->delete();

                $quote->update([
                    'customer_id' => $this->customer_id,
                    'valid_until' => $this->valid_until,
                    'tax_amount' => $this->calculatedTax,
                    'shipping_amount' => $this->shipping_amount ?: 0,
                    'total_amount' => $this->total,
                    'notes' => $this->notes,
                ]);

                foreach ($this->items as $item) {
                    QuotationItem::create([
                        'quotation_id' => $quote->id,
                        'product_id' => $item['is_blank'] ? null : $item['product_id'],
                        'description' => $item['is_blank'] ? $item['description'] : null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => (float)$item['quantity'] * (float)$item['unit_price'],
                    ]);
                }

                DB::commit();
                $this->resetForm();
                if ($this->showDetailsModal && $this->selectedQuoteId === $quote->id) {
                    $this->viewQuote($quote->id);
                }
                $this->dispatch('toast', type: 'success', message: 'Sales Quotation updated successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                $this->dispatch('toast', type: 'error', message: 'Error updating quotation: ' . $e->getMessage());
            }
        } else {
            if (!auth()->user()->can('create quotations')) { abort(403); }
            $this->validate([
                'customer_id' => 'required|exists:customers,id',
                'valid_until' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required_if:items.*.is_blank,false',
                'items.*.description' => 'required_if:items.*.is_blank,true',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
            ], [
                'items.*.product_id.required_if' => 'Please select a product for the line.',
                'items.*.description.required_if' => 'Please provide a description for the blank line.',
                'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            ]);

            DB::beginTransaction();
            try {
                $quotation = Quotation::create([
                    'reference_no' => 'QTE-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                    'customer_id' => $this->customer_id,
                    'status' => 'draft',
                    'valid_until' => $this->valid_until,
                    'total_amount' => $this->total,
                    'tax_amount' => $this->calculatedTax,
                    'shipping_amount' => $this->shipping_amount ?: 0,
                    'notes' => $this->notes,
                ]);

                foreach($this->items as $item) {
                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $item['is_blank'] ? null : $item['product_id'],
                        'description' => $item['is_blank'] ? $item['description'] : null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => (float)$item['quantity'] * (float)$item['unit_price'],
                    ]);
                }

                DB::commit();
                $this->resetForm();
                $this->dispatch('toast', type: 'success', message: 'Draft Sales Quotation created successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                $this->dispatch('toast', type: 'error', message: 'Error creating quote: ' . $e->getMessage());
            }
        }
    }

    public function viewQuote($id)
    {
        $quote = Quotation::with(['customer', 'items.product'])->findOrFail($id);
        $this->selectedQuoteId = $id;
        $this->selectedQuote = $quote;
        $this->showDetailsModal = true;
    }

    public function editQuote($id)
    {
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        $quote = Quotation::with('items')->findOrFail($id);
        $this->editQuoteId = $id;
        $this->customer_id = $quote->customer_id;
        $this->valid_until = $quote->valid_until;
        $this->shipping_amount = $quote->shipping_amount;
        $this->notes = $quote->notes;
        $this->apply_gst = $quote->tax_amount > 0;
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->items = [];
        
        foreach ($quote->items as $item) {
            $this->items[] = [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'is_blank' => $item->product_id ? false : true,
            ];
        }
        
        if (empty($this->items)) {
            $this->items = [['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]];
        }

        $this->isEditing = true;
        $this->viewMode = 'table';
        $this->showDetailsModal = false; // Hide details modal if open so they can edit inline!
        $this->showForm = true;
        $this->dispatch('toast', type: 'success', message: 'Details loaded successfully.');
    }

    public function deleteQuote($id)
    {
        if (!auth()->user()->can('delete quotations')) { abort(403); }
        DB::beginTransaction();
        try {
            $quote = Quotation::findOrFail($id);
            $quote->items()->delete();
            $quote->delete();
            DB::commit();

            $this->showDetailsModal = false;
            $this->resetForm();
            $this->dispatch('toast', type: 'success', message: 'Sales Quotation deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', message: 'Error deleting quotation: ' . $e->getMessage());
        }
    }

    public function updateStatus($id, $status)
    {
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        Quotation::findOrFail($id)->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: "Quotation status updated to {$status}.");
    }

    public function moveQuotationStatus($id, $newStatus)
    {
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        if ($newStatus === 'accepted') {
            $this->convertToSalesOrder($id);
        } else {
            $this->updateStatus($id, $newStatus);
        }
    }

    public function convertToSalesOrder($id)
    {
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        $quote = Quotation::with(['items', 'customer'])->findOrFail($id);

        DB::beginTransaction();
        try {
            $contactPerson = $quote->customer ? $quote->customer->contactPersons()->where('is_primary', true)->first() : null;
            $billingAddress = $quote->customer ? $quote->customer->addresses()->where('type', 'billing')->first() : null;
            $shippingAddress = $quote->customer ? $quote->customer->addresses()->where('type', 'shipping')->first() : null;

            $salesOrder = SalesOrder::create([
                'customer_id' => $quote->customer_id,
                'status' => 'confirmed',
                'total_amount' => $quote->total_amount,
                'tax_amount' => $quote->tax_amount,
                'shipping_amount' => $quote->shipping_amount,
                'notes' => $quote->notes,
                'contact_person_id' => $contactPerson?->id,
                'billing_address_id' => $billingAddress?->id,
                'shipping_address_id' => $shippingAddress?->id,
            ]);

            foreach($quote->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);
            }

            foreach($quote->items as $item) {
                if ($item->product_id) {
                    $binStock = \App\Models\BinProductStock::where('product_id', $item->product_id)
                         ->where('quantity', '>', 0)
                         ->orderBy('quantity', 'desc')
                         ->first();

                    if ($binStock) {
                        $qtyToReserve = min($item->quantity, $binStock->quantity);
                        $binStock->quantity -= $qtyToReserve;
                        $binStock->save();

                        \App\Models\InventoryTransaction::create([
                            'product_id' => $item->product_id,
                            'from_bin_id' => $binStock->warehouse_bin_id,
                            'to_bin_id' => null,
                            'type' => 'OUT',
                            'quantity' => $qtyToReserve,
                            'reference_type' => 'sales_order_reservation',
                            'reference_id' => $salesOrder->id,
                            'notes' => 'Auto-reserved from approved Quote ' . $quote->reference_no,
                            'user_id' => auth()->id(),
                        ]);
                    }
                }
            }

            if ($quote->crm_lead_id) {
                $lead = \App\Models\CrmLead::find($quote->crm_lead_id);
                if ($lead) {
                    $lead->update([
                        'pipeline_stage' => 'won',
                        'deal_probability' => 100,
                    ]);
                    \App\Models\CrmActivity::create([
                        'crm_lead_id' => $lead->id,
                        'type' => 'meeting',
                        'description' => "Quotation approved! Auto-converted Quotation {$quote->reference_no} into Sales Order SO-{$salesOrder->id}",
                        'activity_date' => now()->toDateString(),
                        'user_id' => auth()->id() ?? \App\Models\User::first()?->id,
                    ]);
                }
            }

            $quote->update(['status' => 'accepted']);
            DB::commit();
            
            if ($this->showDetailsModal && $this->selectedQuoteId === $quote->id) {
                $this->viewQuote($quote->id);
            }
            $this->dispatch('toast', type: 'success', message: "Quotation approved! Auto-created Sales Order SO-{$salesOrder->id} successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', message: 'Error converting quote: ' . $e->getMessage());
        }
    }
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Actions & View Mode Toggle -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Sales Quotation Management</h1>
            <p class="text-xs text-gray-500 mt-1">Generate dynamic customer pricing estimates, track pipeline approvals, and instantly convert accepted quotes into physical SCM orders.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <!-- Segment Controls (List / Kanban) -->
            <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200">
                <button type="button" 
                        wire:click="$set('viewMode', 'table')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 {{ $viewMode === 'table' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                    List View
                </button>
                <button type="button" 
                        wire:click="$set('viewMode', 'kanban')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 {{ $viewMode === 'kanban' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                    Kanban Board
                </button>
            </div>

            @can('create quotations')
                <button type="button" wire:click="toggleForm" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors shadow-indigo-500/20">
                    {{ $showForm ? 'Close Form' : '+ New Sales Quote' }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Financial KPI Summary Cards -->
    @php
        $totalQuoteVolume = \App\Models\Quotation::sum('total_amount');
        $acceptedCount = \App\Models\Quotation::where('status', 'accepted')->count();
        $totalCount = \App\Models\Quotation::count();
        $pendingQuotes = \App\Models\Quotation::whereIn('status', ['draft', 'sent'])->count();
        $winRate = $totalCount > 0 ? ($acceptedCount / $totalCount) * 100 : 0;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 animate-fade-in">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Total Quoted Volume</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalQuoteVolume, 2) }}</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Across {{ $totalCount }} pricing estimates</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Converted / Accepted</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $acceptedCount }}</h3>
            <span class="text-[10px] text-emerald-600 font-bold block mt-1">Successfully won and converted to SO</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Pending Estimates</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $pendingQuotes }}</h3>
            <span class="text-[10px] text-amber-600 font-bold block mt-1">Estimates in draft or sent state</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Quotation Win Rate</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ number_format($winRate, 1) }}%</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Percentage of quotes accepted</span>
        </div>
    </div>

    <!-- Inline Creator and Editor Desk Workspace -->
    @if($viewMode === 'table' && $showForm)
    <div class="animate-slide-down">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">
                    {{ $isEditing ? 'Edit Commercial Sales Quotation' : 'Create Commercial Sales Quotation' }}
                </h3>
                <p class="text-xs text-slate-550">
                    {{ $isEditing ? 'Edit fields directly inside the corporate quotation document layout below.' : 'Fill in the fields directly on the sales estimate document below.' }}
                </p>
            </div>
            <button type="button" wire:click="resetForm" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200 rounded-xl transition-colors" title="Close Form">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @php
            $selectedCust = $this->getCustomersList()->firstWhere('id', $customer_id);
        @endphp
        <form wire:submit.prevent="saveQuote">
            <div class="bg-white shadow-2xl border border-slate-200 p-12 relative overflow-hidden text-xs text-slate-755 font-semibold" style="min-height: 1000px; font-family: 'Inter', system-ui, sans-serif;">
                <!-- Top branding accent border -->
                <div class="absolute top-0 left-0 right-0 h-2 bg-indigo-600"></div>

                <!-- Document Header -->
                <div class="flex justify-between items-start border-b-2 border-indigo-600 pb-6 mb-8 mt-2">
                    <div>
                        @if(setting('website_logo'))
                            <img src="{{ setting('website_logo') }}" alt="Logo" class="max-h-12 mb-2 object-contain">
                        @else
                            <h1 class="text-xl font-black text-slate-800 tracking-tight">{{ setting('website_name', 'SCM ERP System') }}</h1>
                        @endif
                        <div class="text-[10px] text-slate-550 whitespace-pre-line mt-1 font-semibold leading-relaxed">
                            {!! nl2br(e(setting('company_location', "Corporate Logistics, Warehousing & Supply Chain Operations Hub.\nJebel Ali Free Zone, Dubai, United Arab Emirates"))) !!}
                        </div>
                    </div>
                    <div class="text-right">
                        <h2 class="text-3xl font-black text-indigo-600 font-mono uppercase tracking-tight">Quotation</h2>
                        <div class="text-xs text-slate-500 font-mono font-bold mt-1">
                            {{ $isEditing ? 'QTE-#' . $editQuoteId : 'QTE-DRAFT' }}
                        </div>
                        <div class="text-[10px] text-slate-550 font-mono font-bold mt-2 space-y-1">
                            <div>
                                <span class="text-slate-400">Valid Until:</span>
                                <input type="date" wire:model.live="valid_until" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] font-bold font-mono p-0.5 w-28 ml-1 rounded" required>
                                <x-input-error :messages="$errors->get('valid_until')" class="mt-0.5 text-right text-[10px]" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Panels -->
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Target Customer Client *</div>
                        <div class="text-xs text-slate-700 leading-relaxed font-medium">
                            <select wire:model.live="customer_id" class="w-full border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs p-1 text-slate-800 font-bold mb-2 rounded cursor-pointer" required>
                                <option value="">-- Choose Customer --</option>
                                @foreach($this->getCustomersList() as $cust)
                                    <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />

                            @if($selectedCust)
                                @if($selectedCust->company_name)
                                    <p class="text-indigo-600 font-bold mb-1">{{ $selectedCust->company_name }}</p>
                                @endif
                                <span class="text-slate-500">Email:</span> {{ $selectedCust->email ?? 'N/A' }}<br>
                                <span class="text-slate-500">Phone:</span> {{ $selectedCust->phone ?? 'N/A' }}
                            @else
                                <span class="text-slate-400 italic">Please select a customer to populate recipient details.</span>
                            @endif
                        </div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Shipping Details</div>
                        <div class="text-xs text-slate-600 leading-relaxed font-medium">
                            @if($selectedCust && $selectedCust->shipping_address)
                                <strong>Delivery Address:</strong><br>
                                <span class="block text-slate-600 mt-1 whitespace-pre-line">{{ $selectedCust->shipping_address }}</span>
                            @else
                                <strong>Delivery Address:</strong><br>
                                As per standard SCM terms.
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-8">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                <th class="p-3 text-left w-12 rounded-l-xl">#</th>
                                <th class="p-3 text-left">Item Description / Product selection</th>
                                <th class="p-3 text-center w-20">Quantity</th>
                                <th class="p-3 text-right w-24">Unit Price</th>
                                <th class="p-3 text-right w-24">Subtotal</th>
                                <th class="p-3 text-right w-24">Total</th>
                                <th class="p-3 text-center w-12 rounded-r-xl"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @php $lineIndex = 1; @endphp
                            @foreach($items as $idx => $item)
                                @php
                                    $lineSub = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                                    $totalSub = (float)$this->subtotal;
                                    $lineTax = $totalSub > 0 ? ($lineSub / $totalSub) * (float)$this->calculatedTax : 0;
                                    $lineTot = $lineSub + $lineTax;
                                @endphp
                                <tr class="font-medium hover:bg-slate-50/30 transition-colors">
                                    <td class="p-3 text-slate-400">{{ $lineIndex++ }}</td>
                                    <td class="p-3">
                                        @if($item['is_blank'])
                                            <input type="text" wire:model.live="items.{{ $idx }}.description" class="w-full border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-bold p-1 text-slate-800 rounded" placeholder="Enter custom item or service description..." required>
                                            <x-input-error :messages="$errors->get('items.'.$idx.'.description')" class="mt-1" />
                                        @else
                                            <select wire:model.live="items.{{ $idx }}.product_id" class="w-full border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-bold p-1 text-slate-855 rounded" required>
                                                <option value="">-- Choose Product --</option>
                                                @foreach($this->getProductsList() as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('items.'.$idx.'.product_id')" class="mt-1" />
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.quantity" class="w-16 text-center border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono font-bold p-1 text-slate-800 rounded" required>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end">
                                            <span class="text-slate-400 font-mono mr-1">{{ setting('currency_symbol', '$') }}</span>
                                            <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.unit_price" class="w-20 text-right border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono p-1 text-slate-800 rounded" required>
                                        </div>
                                    </td>
                                    <td class="p-3 text-right text-slate-600 font-mono">
                                        {{ setting('currency_symbol', '$') }}{{ number_format($lineSub, 2) }}
                                    </td>
                                    <td class="p-3 text-right text-slate-800 font-mono font-bold">
                                        {{ setting('currency_symbol', '$') }}{{ number_format($lineTot, 2) }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if(count($items) > 1)
                                            <button type="button" wire:click="removeItemLine({{ $idx }})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <!-- Add lines controller directly under the table -->
                    <div class="flex items-center gap-3 mt-4 justify-start">
                        <button type="button" wire:click="addBlankLine" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-755 text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1">
                            + Add Blank Row
                        </button>
                        <button type="button" wire:click="addItemLine" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-100 transition-colors flex items-center gap-1">
                            + Add Product Line
                        </button>
                    </div>
                </div>

                <!-- Notes & Totals -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start border-t border-slate-150 pt-6">
                    <div class="md:col-span-7 bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Remarks / SCM Terms</div>
                            <textarea wire:model.live.debounce.150ms="notes" rows="3" class="w-full border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-semibold p-1.5 rounded" placeholder="Optional notes to customer, installation info, deposit terms..."></textarea>
                        </div>
                        <div class="border-t border-slate-200 pt-3 flex flex-wrap items-center gap-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-bold">
                                <input type="checkbox" wire:model.live="apply_gst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                <span class="text-xs font-bold text-slate-705">Apply GST Tax</span>
                            </label>

                            @if($apply_gst)
                                <div class="inline-flex items-center gap-3 p-1.5 bg-white rounded-xl border border-slate-150">
                                    <div>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase mr-1">Type:</span>
                                        <select wire:model.live="gst_type" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] p-0 font-bold rounded cursor-pointer">
                                            <option value="exclusive">Exclusive</option>
                                            <option value="inclusive">Inclusive</option>
                                        </select>
                                    </div>
                                    <div class="border-l border-slate-200 pl-2">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase mr-1">Rate:</span>
                                        <input type="number" step="0.01" wire:model.live="gst_percentage" class="w-10 border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] text-right p-0 font-mono font-bold rounded">%
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="md:col-span-5">
                        <table class="w-full text-xs font-semibold text-slate-600">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">Subtotal:</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($this->subtotal, 2) }}</td>
                                </tr>
                                @if($apply_gst && $this->calculatedTax > 0)
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">GST ({{ $gst_percentage }}% {{ ucfirst($gst_type) }}):</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($this->calculatedTax, 2) }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">Shipping &amp; Handling:</td>
                                    <td class="py-2.5 text-right">
                                        <div class="flex items-center justify-end">
                                            <span class="text-slate-400 font-mono mr-1">{{ setting('currency_symbol', '$') }}</span>
                                            <input type="number" step="0.01" wire:model.live="shipping_amount" class="w-20 text-right border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono font-bold p-0.5 rounded">
                                        </div>
                                    </td>
                                </tr>
                                <tr class="border-t-2 border-slate-200">
                                    <td class="py-3 text-left font-black text-slate-900 text-sm">Grand Total:</td>
                                    <td class="py-3 text-right font-mono font-black text-indigo-600 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($this->total, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="grid grid-cols-2 gap-8 mt-12 pt-8 border-t border-slate-200">
                    <div class="text-center">
                        <div class="h-8 border-b border-slate-300 mx-4 mb-2"></div>
                        <div class="text-[10px] font-bold text-slate-700 uppercase">Prepared By</div>
                        <div class="text-[9px] text-slate-400">Representative SCM Sales Team</div>
                    </div>
                    <div class="text-center">
                        <div class="h-8 border-b border-slate-300 mx-4 mb-2"></div>
                        <div class="text-[10px] font-bold text-slate-700 uppercase">Accepted By Customer Client</div>
                        <div class="text-[9px] text-slate-400">Authorized Client Signature</div>
                    </div>
                </div>

                <!-- Page Footer -->
                <div class="absolute bottom-6 left-12 right-12 flex justify-between items-center text-[10px] text-slate-400 font-medium">
                    <span>{{ setting('website_name', 'SCM ERP System') }} &copy; {{ date('Y') }}. All rights reserved.</span>
                    <span>Page 1 of 1</span>
                </div>
            </div>

            <!-- Premium High-Visibility Action Toolbar -->
            <div class="mt-8 p-4 bg-slate-50/90 backdrop-blur rounded-2xl border border-slate-200/80 shadow-lg flex justify-end gap-3 no-print">
                <button type="button" wire:click="resetForm" class="px-6 py-3 bg-white hover:bg-slate-50 text-slate-705 text-xs font-black uppercase tracking-wider rounded-xl border border-slate-300 shadow-sm transition-all active:scale-[0.98]">
                    Cancel &amp; Close
                </button>
                <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md hover:shadow-indigo-500/20 transition-all active:scale-[0.98] cursor-pointer">
                    {{ $isEditing ? 'Save & Update Quotation' : 'Save & Create Quotation' }}
                </button>
            </div>
        </form>
    </div>
    @endif

    @if($viewMode === 'table')
        <!-- Quotations Table (List View) -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-150">
                    <thead>
                        <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                            <th class="px-4 py-3 rounded-l-xl">Quote Ref / Validity</th>
                            <th class="px-4 py-3">Customer Client</th>
                            <th class="px-4 py-3">Quoted Items Summary</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Quote Total</th>
                            <th class="px-4 py-3 text-right rounded-r-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-sm">
                        @foreach($this->getQuotesList() as $q)
                            <tr class="hover:bg-gray-50/20 transition-colors">
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-extrabold text-gray-900">{{ $q->reference_no }}</div>
                                    <div class="text-[10px] text-gray-400 mt-0.5 font-semibold">Valid until: {{ date('M d, Y', strtotime($q->valid_until)) }}</div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-gray-900">{{ $q->customer->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-indigo-600 font-semibold mt-0.5">{{ $q->customer->company_name ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-gray-500 max-w-xs truncate">
                                    @foreach($q->items as $itm)
                                        <div class="truncate">{{ $itm->quantity }}x {{ $itm->product_id ? ($itm->product->name ?? 'Unknown') : ($itm->description ?? 'Custom Line') }}</div>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    @php
                                        $badge = 'bg-gray-50 text-gray-700 border border-gray-100';
                                        if ($q->status === 'accepted') $badge = 'bg-green-50 text-green-700 border border-green-100';
                                        elseif ($q->status === 'sent') $badge = 'bg-blue-50 text-blue-700 border border-blue-100';
                                        elseif ($q->status === 'rejected' || $q->status === 'expired') $badge = 'bg-rose-50 text-rose-700 border border-rose-100';
                                    @endphp
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ $badge }}">
                                        {{ $q->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap font-mono text-right font-extrabold text-gray-900">
                                    {{ setting('currency_symbol', '$') }}{{ number_format($q->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-right text-xs font-bold space-x-2">
                                    <a href="{{ route('pdf.quotation', $q->id) }}" target="_blank" class="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors inline-flex items-center" title="PDF Preview & Download">
                                        <!-- PDF/File Icon -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                    
                                    @can('edit quotations')
                                        @if($q->status !== 'accepted')
                                            <button type="button" wire:click="editQuote({{ $q->id }})" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-lg transition-colors inline-flex items-center" title="Edit Quotation">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        @endif
                                    @endcan
                                    
                                    @if($q->status === 'draft')
                                        <button type="button" wire:click="updateStatus({{ $q->id }}, 'sent')" class="px-2 py-0.5 text-[10px] font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 rounded transition-colors shadow-sm inline-block align-middle">Mark Sent</button>
                                    @endif
                                    @if($q->status === 'sent')
                                        <button type="button" wire:click="convertToSalesOrder({{ $q->id }})" class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded transition-colors shadow-sm inline-block align-middle">Accept & Convert</button>
                                        <button type="button" wire:click="updateStatus({{ $q->id }}, 'rejected')" class="px-2 py-0.5 text-[10px] font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded transition-colors shadow-sm inline-block align-middle">Reject</button>
                                    @endif
                                    @if($q->status === 'accepted')
                                        <span class="text-gray-400 italic text-[11px] inline-block align-middle">Converted to SO</span>
                                    @endif

                                    @can('delete quotations')
                                        <button type="button" onclick="confirm('Are you sure you want to permanently delete this quotation estimate?') && @this.deleteQuote({{ $q->id }})" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors inline-flex items-center" title="Delete Quotation">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Quotation Kanban Pipeline View -->
        <div class="flex overflow-x-auto gap-6 pb-6 select-none scrollbar-thin" style="scrollbar-width: thin; -webkit-overflow-scrolling: touch;">
            @foreach([
                'draft' => ['name' => 'Draft Estimate', 'bg' => 'bg-slate-100/50', 'border' => 'border-slate-200', 'text' => 'text-slate-600'],
                'sent' => ['name' => 'Sent to Client', 'bg' => 'bg-blue-50/30', 'border' => 'border-blue-100', 'text' => 'text-blue-600'],
                'accepted' => ['name' => 'Accepted & Approved 🎉', 'bg' => 'bg-emerald-50/30', 'border' => 'border-emerald-100', 'text' => 'text-emerald-600'],
                'rejected' => ['name' => 'Rejected', 'bg' => 'bg-rose-50/20', 'border' => 'border-rose-100', 'text' => 'text-rose-600']
            ] as $statusKey => $statusVal)
                
                @php
                    $quotes = $this->getQuotesList();
                    $statusQuotes = $quotes->where('status', $statusKey);
                    $statusTotal = $statusQuotes->sum('total_amount');
                @endphp

                <div x-data="{ draggingOver: false }"
                     x-on:dragenter.prevent="draggingOver = true"
                     x-on:dragleave.prevent="draggingOver = false"
                     x-on:dragover.prevent=""
                     x-on:drop="draggingOver = false; const quoteId = event.dataTransfer.getData('text/plain'); $wire.moveQuotationStatus(quoteId, '{{ $statusKey }}')"
                     class="flex-shrink-0 w-[290px] lg:w-[310px] rounded-3xl p-4 transition-all duration-200 border space-y-4 {{ $statusVal['bg'] }} {{ $statusVal['border'] }}"
                     :class="{ 'ring-2 ring-indigo-600 bg-indigo-50/15 border-indigo-200 shadow-md scale-[1.01]': draggingOver }"
                >
                    <!-- Column Header -->
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <div>
                            <h4 class="text-sm font-black text-gray-900">{{ $statusVal['name'] }}</h4>
                            <span class="text-[10px] text-gray-400 font-bold font-mono">{{ $statusQuotes->count() }} estimates</span>
                        </div>
                        <span class="text-xs font-black {{ $statusVal['text'] }} font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($statusTotal, 0) }}</span>
                    </div>

                    <!-- Column Cards list -->
                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        @forelse($statusQuotes as $quote)
                            <div draggable="true"
                                 x-on:dragstart="event.dataTransfer.setData('text/plain', {{ $quote->id }})"
                                 wire:click="editQuote({{ $quote->id }})"
                                 class="bg-white p-4 rounded-2xl border border-gray-150 shadow-sm relative group hover:shadow-md hover:border-indigo-600 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"
                            >
                                <div class="flex justify-between items-start gap-1">
                                    <div class="text-xs font-extrabold text-gray-900 leading-snug pr-4">{{ $quote->reference_no }}</div>
                                    <a href="{{ route('pdf.quotation', $quote->id) }}" target="_blank" onclick="event.stopPropagation()" class="p-1 text-indigo-600 hover:bg-indigo-50 rounded" title="PDF Preview">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                </div>
                                <div class="text-[10px] font-bold text-indigo-600 mt-0.5 truncate">{{ $quote->customer->name ?? 'N/A' }}</div>
                                @if($quote->customer && $quote->customer->company_name)
                                    <div class="text-[9px] text-gray-400 mt-0.5 truncate">{{ $quote->customer->company_name }}</div>
                                @endif
                                <div class="text-[9px] text-gray-400 mt-1 font-semibold">Valid until: {{ date('M d, Y', strtotime($quote->valid_until)) }}</div>

                                <!-- Items preview -->
                                <div class="mt-2 text-[9px] text-gray-400 border-t border-slate-100 pt-2 space-y-0.5 max-h-[60px] overflow-hidden font-medium">
                                    @foreach($quote->items as $itm)
                                        <div class="truncate">{{ $itm->quantity }}x {{ $itm->product_id ? ($itm->product->name ?? 'Unknown') : ($itm->description ?? 'Custom Line') }}</div>
                                    @endforeach
                                </div>

                                <!-- Total Value -->
                                <div class="mt-3 flex items-baseline justify-between border-t border-slate-50 pt-2">
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">Total:</span>
                                    <span class="text-xs font-mono font-black text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($quote->total_amount, 2) }}</span>
                                </div>

                                <!-- Card Actions Row -->
                                <div class="border-t border-gray-50 pt-2.5 mt-2.5 flex items-center justify-between gap-1 text-[9px] font-bold" onclick="event.stopPropagation()">
                                    <div class="flex items-center gap-2">
                                        @if($quote->status === 'draft')
                                            <button type="button" wire:click.stop="updateStatus({{ $quote->id }}, 'sent')" class="text-indigo-600 hover:text-indigo-800">
                                                Mark Sent
                                            </button>
                                        @elseif($quote->status === 'sent')
                                            <button type="button" wire:click.stop="convertToSalesOrder({{ $quote->id }})" class="text-emerald-600 hover:text-emerald-800">
                                                Approve SO
                                            </button>
                                        @endif
                                    </div>

                                    @if($quote->status !== 'accepted' && $quote->status !== 'rejected')
                                        <select 
                                            onchange="event.stopPropagation(); @this.moveQuotationStatus({{ $quote->id }}, this.value)"
                                            onclick="event.stopPropagation()"
                                            class="p-0.5 text-[8px] bg-slate-50 border-gray-200 text-gray-600 rounded focus:ring-0 focus:border-indigo-600"
                                        >
                                            <option value="">Move...</option>
                                            @foreach(['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected'] as $k => $v)
                                                @if($k !== $quote->status)
                                                    <option value="{{ $k }}">{{ $v }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-center py-6 text-xs text-slate-400 italic">No quotes in {{ strtolower($statusVal['name']) }}.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Quotation Details Modal (Corporate Letterhead Document) -->
    @if($showDetailsModal && $selectedQuote)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity no-print" aria-hidden="true" wire:click="$set('showDetailsModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen no-print" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full p-8 border border-gray-200 relative">
                    
                    <!-- Close and Print Header Row (no-print) -->
                    <div class="flex justify-between items-center pb-4 mb-6 border-b border-slate-100 no-print">
                        <h3 class="text-base font-black text-slate-800">Commercial Estimation Letterhead</h3>
                        <div class="flex items-center gap-2">
                            @can('edit quotations')
                                @if($selectedQuote->status !== 'accepted')
                                    <button type="button" wire:click="editQuote({{ $selectedQuote->id }})" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                                        Edit Estimate
                                    </button>
                                @endif
                            @endcan
                            <button onclick="window.print()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print / Export PDF
                            </button>
                            <button wire:click="$set('showDetailsModal', false)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <style>
                        @media print {
                            body {
                                background: white !important;
                                color: black !important;
                                -webkit-print-color-adjust: exact !important;
                                print-color-adjust: exact !important;
                            }
                            body * {
                                visibility: hidden;
                            }
                            #printable-quote-area, #printable-quote-area * {
                                visibility: visible !important;
                            }
                            #printable-quote-area {
                                position: fixed !important;
                                left: 0 !important;
                                top: 0 !important;
                                width: 100% !important;
                                height: 100% !important;
                                z-index: 9999999 !important;
                                background: white !important;
                                color: black !important;
                                padding: 1.5cm !important;
                                margin: 0 !important;
                                box-shadow: none !important;
                                border: none !important;
                                visibility: visible !important;
                            }
                            .no-print {
                                display: none !important;
                            }
                        }
                    </style>

                    <!-- A4 Printable Document Area -->
                    <div id="printable-quote-area" class="space-y-8 text-xs text-slate-800">
                        
                        <!-- Corporate Header -->
                        <div class="flex justify-between items-start border-b-2 border-slate-900 pb-6">
                            <div>
                                <div class="flex items-center gap-2">
                                    @if(setting('website_logo'))
                                        <img src="{{ setting('website_logo') }}" class="h-8 object-contain" alt="Logo">
                                    @else
                                        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white text-base font-black shadow-md">S</div>
                                    @endif
                                    <span class="text-lg font-black text-slate-900 tracking-tight">{{ setting('website_name', 'SCM ENTERPRISE SUITE') }}</span>
                                </div>
                                <p class="text-[9px] text-slate-500 mt-1 font-bold">EXCELLENCE IN GLOBAL SUPPLY CHAINS</p>
                                <p class="text-[9px] text-slate-400 mt-0.5 whitespace-pre-wrap">{!! nl2br(e(setting('company_location', '100 Logistics Blvd, Warehouse District'))) !!}</p>
                                <p class="text-[9px] text-slate-400">billing@scm-erp.example.com</p>
                            </div>
                            
                            <div class="text-right">
                                <h2 class="text-xl font-black text-slate-900 tracking-tight">COMMERCIAL ESTIMATE</h2>
                                <p class="text-[10px] font-bold text-slate-600 mt-1 font-mono">REF: {{ $selectedQuote->reference_no }}</p>
                                <p class="text-[9px] text-slate-400 mt-0.5">Valid Until: {{ date('M d, Y', strtotime($selectedQuote->valid_until)) }}</p>
                                
                                @php
                                    $quoteClrs = [
                                        'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
                                        'sent' => 'bg-blue-50 text-blue-700 border-blue-100',
                                        'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                        'rejected' => 'bg-rose-50 text-rose-700 border-rose-100'
                                    ];
                                @endphp
                                <span class="inline-block mt-2 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase border {{ $quoteClrs[$selectedQuote->status] ?? 'bg-slate-100' }}">
                                    {{ $selectedQuote->status }}
                                </span>
                            </div>
                        </div>

                        <!-- Bill To & Bill From Addresses -->
                        <div class="grid grid-cols-2 gap-8 border-b border-slate-100 pb-6">
                            <div>
                                <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider block">PREPARED FOR CLIENT:</span>
                                <div class="mt-1">
                                    <p class="text-sm font-black text-slate-900">{{ $selectedQuote->customer->name ?? 'N/A' }}</p>
                                    @if($selectedQuote->customer && $selectedQuote->customer->company_name)
                                        <p class="font-bold text-indigo-600">{{ $selectedQuote->customer->company_name }}</p>
                                    @endif
                                    <p class="text-slate-500 font-medium mt-1 font-mono">Email: {{ $selectedQuote->customer->email ?? '--' }}</p>
                                    <p class="text-slate-500 font-medium font-mono">Phone: {{ $selectedQuote->customer->phone ?? '--' }}</p>
                                </div>
                            </div>
                            
                            <div>
                                <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider block">PREPARED BY REPRESENTATIVE:</span>
                                <div class="mt-1">
                                    <p class="text-sm font-black text-slate-900">SCM Sales & Procurement Team</p>
                                    <p class="text-slate-500 font-medium">Automated Enterprise Routing</p>
                                    <p class="text-slate-500 font-medium font-mono">Date Issued: {{ $selectedQuote->created_at->format(setting('date_format', 'Y-m-d')) }}</p>
                                    <p class="text-slate-500 font-medium font-mono">Currency: {{ setting('currency_symbol', '$') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item ledger table -->
                        <div>
                            <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider block mb-3">PRODUCT QUANTITIES & PRICING SPECIFICATIONS</span>
                            <table class="min-w-full divide-y divide-slate-200 text-left">
                                <thead>
                                    <tr class="text-[9px] text-slate-400 uppercase font-black tracking-wider bg-slate-50">
                                        <th class="px-3 py-2 font-black">#</th>
                                        <th class="px-3 py-2 font-black">Product Details</th>
                                        <th class="px-3 py-2 font-black">SKU</th>
                                        <th class="px-3 py-2 text-right font-black">Quantity</th>
                                        <th class="px-3 py-2 text-right font-black">Unit Price</th>
                                        <th class="px-3 py-2 text-right font-black rounded-r">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($selectedQuote->items as $idx => $itm)
                                        <tr class="font-medium text-slate-700">
                                            <td class="px-3 py-2.5 font-bold font-mono">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-2.5">
                                                <div class="font-extrabold text-slate-900">{{ $itm->product_id ? ($itm->product->name ?? 'Unknown') : ($itm->description ?? 'Custom Line') }}</div>
                                            </td>
                                            <td class="px-3 py-2.5 font-mono text-[10px] text-slate-500">{{ $itm->product->sku ?? '--' }}</td>
                                            <td class="px-3 py-2.5 text-right font-mono font-bold">{{ number_format($itm->quantity, 2) }}</td>
                                            <td class="px-3 py-2.5 text-right font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($itm->unit_price, 2) }}</td>
                                            <td class="px-3 py-2.5 text-right font-mono font-black text-slate-900">{{ setting('currency_symbol', '$') }}{{ number_format($itm->total_price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Calculations and Grand Total block -->
                        <div class="flex justify-end pt-4 border-t border-slate-150">
                            <div class="w-64 space-y-1.5 text-[10px] font-semibold text-slate-500">
                                <div class="flex justify-between">
                                    <span>SUBTOTAL VALUE:</span>
                                    <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($selectedQuote->total_amount - $selectedQuote->tax_amount - $selectedQuote->shipping_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>ESTIMATED TAX:</span>
                                    <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($selectedQuote->tax_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>SHIPPING / FREIGHT:</span>
                                    <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($selectedQuote->shipping_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between border-t border-slate-200 pt-2 text-xs font-black">
                                    <span class="text-slate-800">GRAND TOTAL:</span>
                                    <span class="font-mono text-indigo-600">{{ setting('currency_symbol', '$') }}{{ number_format($selectedQuote->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Notes/Memo -->
                        @if($selectedQuote->notes)
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 text-[10px] leading-relaxed">
                                <span class="font-extrabold uppercase tracking-wider block text-slate-400 mb-1">Commercial Notes & SCM Terms:</span>
                                <p class="text-slate-600 font-medium whitespace-pre-wrap">{{ $selectedQuote->notes }}</p>
                            </div>
                        @endif

                        <!-- Security Token & Accept Signatures -->
                        <div class="pt-8 border-t border-slate-200 grid grid-cols-1 md:grid-cols-2 gap-8 items-end">
                            <div>
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block font-mono">ERP SECURE VALIDATION TOKEN</span>
                                <span class="text-[9px] font-mono text-slate-550 block truncate mt-1 bg-slate-50 p-1.5 rounded border border-slate-100" title="Secure SHA-256 ERP Verification Hash">
                                    {{ hash('sha256', $selectedQuote->reference_no . '|' . $selectedQuote->total_amount . '|' . $selectedQuote->valid_until) }}
                                </span>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="border-b border-slate-400 h-8"></div>
                                <div class="flex justify-between text-[9px] text-slate-455 font-bold uppercase tracking-wider">
                                    <span>Authorized Client Signature</span>
                                    <span>Date Signed</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Client Conversions / Deletions Row (no-print) -->
                    <div class="flex justify-between items-center border-t border-slate-100 pt-6 mt-6 no-print">
                        <div class="flex items-center gap-2">
                            @if($selectedQuote->status === 'draft')
                                <button type="button" wire:click="updateStatus({{ $selectedQuote->id }}, 'sent')" class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-755 text-xs font-bold rounded-xl transition-colors">
                                    Mark Sent &amp; Dispatch Estimate
                                </button>
                            @endif
                            @if($selectedQuote->status === 'sent')
                                <button type="button" wire:click="convertToSalesOrder({{ $selectedQuote->id }})" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-750 text-xs font-bold rounded-xl transition-colors">
                                    Accept Quote &amp; Auto-Launch Sales Order
                                </button>
                                <button type="button" wire:click="updateStatus({{ $selectedQuote->id }}, 'rejected')" class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl transition-colors">
                                    Mark Rejected
                                </button>
                            @endif
                        </div>
                        
                        @can('delete quotations')
                            <button type="button" 
                                    onclick="confirm('Are you sure you want to permanently delete this quotation estimate?') && @this.deleteQuote({{ $selectedQuote->id }})" 
                                    class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-755 text-xs font-bold rounded-xl transition-colors"
                            >
                                Delete Quotation
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

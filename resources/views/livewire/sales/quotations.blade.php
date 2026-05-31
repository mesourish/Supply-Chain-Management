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
    
    public $showEditModal = false;
    public $editCustomerId = '';
    public $editValidUntil = '';
    public $editTaxAmount = 0.00;
    public $editShippingAmount = 0.00;
    public $editNotes = '';
    public $editItems = [];

    // Form fields (create)
    public $customer_id = '';
    public $crm_lead_id = null;
    public $valid_until = '';
    public $tax_amount = 0.00;
    public $shipping_amount = 0.00;
    public $notes = '';
    
    public $apply_gst = false;
    public $gst_type = 'exclusive';
    public $gst_percentage = 0;
    
    // Item lines (array of products and details)
    public $items = []; // array of ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
    
    public $showCreateModal = false;
    
    public function mount()
    {
        if (!auth()->user()->can('view quotations')) { abort(403); }
        $this->valid_until = now()->addDays(30)->toDateString();
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
        $this->valid_until = now()->addDays(30)->toDateString();
        $this->tax_amount = 0.00;
        $this->shipping_amount = 0.00;
        $this->notes = '';
        $this->apply_gst = false;
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->items = [
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
        ];
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

    public function addEditItemLine()
    {
        $this->editItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false];
    }

    public function addEditBlankLine()
    {
        $this->editItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true];
    }

    public function removeEditItemLine($index)
    {
        if (count($this->editItems) > 1) {
            unset($this->editItems[$index]);
            $this->editItems = array_values($this->editItems);
        }
    }

    public function updated($key, $value)
    {
        // If product_id in items or editItems changes, automatically pull and populate its base unit price
        if (str_contains($key, '.product_id')) {
            $parts = explode('.', $key);
            $arrayName = $parts[0]; // 'items' or 'editItems'
            $index = $parts[1] ?? null;
            
            if ($index !== null && isset($this->{$arrayName}[$index])) {
                $productId = $value;
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $this->{$arrayName}[$index]['unit_price'] = $product->unit_price;
                    }
                }
            }
        }
    }

    public function getSubtotalProperty()
    {
        $sub = 0;
        foreach($this->items as $item) {
            $sub += ((float)$item['quantity'] * (float)$item['unit_price']);
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

    public function getEditSubtotalProperty()
    {
        $sub = 0;
        foreach($this->editItems as $item) {
            $sub += ((float)$item['quantity'] * (float)$item['unit_price']);
        }
        return $sub;
    }

    public $editApplyGst = false;
    public $editGstType = 'exclusive';

    public function getEditCalculatedTaxProperty()
    {
        if (!$this->editApplyGst) return 0;
        $sub = $this->editSubtotal;
        if ($this->editGstType === 'inclusive') {
            return $sub - ($sub / (1 + ($this->gst_percentage / 100)));
        }
        return $sub * ($this->gst_percentage / 100);
    }

    public function getEditTotalProperty()
    {
        $sub = $this->editSubtotal;
        if ($this->editApplyGst && $this->editGstType === 'inclusive') {
            return $sub + (float)$this->editShippingAmount;
        }
        return $sub + $this->editCalculatedTax + (float)$this->editShippingAmount;
    }

    public function saveQuote()
    {
        if (!auth()->user()->can('create quotations')) { abort(403); }
        $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'valid_until' => 'required|date',
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
            $this->showCreateModal = false;
            $this->resetForm();
            $this->dispatch('toast', type: 'success', message:  'Draft Sales Quotation created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', message:  'Error creating quote: ' . $e->getMessage());
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
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        $quote = Quotation::with('items')->findOrFail($id);
        $this->selectedQuoteId = $id;
        $this->editCustomerId = $quote->customer_id;
        $this->editValidUntil = $quote->valid_until;
        $this->editTaxAmount = $quote->tax_amount;
        $this->editShippingAmount = $quote->shipping_amount;
        $this->editNotes = $quote->notes;
        $this->editApplyGst = $quote->tax_amount > 0;
        $this->editGstType = setting('default_gst_type', 'exclusive'); // Just use default or try to reverse calc
        $this->editItems = [];
        
        foreach ($quote->items as $item) {
            $this->editItems[] = [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'is_blank' => $item->product_id ? false : true,
            ];
        }
        
        if (empty($this->editItems)) {
            $this->editItems = [['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]];
        }

        $this->showEditModal = true;
    }

    public function updateQuote()
    {
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        $this->validate([
            'editCustomerId' => 'required|exists:customers,id',
            'editValidUntil' => 'required|date',
            'editItems.*.product_id' => 'required_if:editItems.*.is_blank,false',
            'editItems.*.description' => 'required_if:editItems.*.is_blank,true',
            'editItems.*.quantity' => 'required|numeric|min:0.01',
            'editItems.*.unit_price' => 'required|numeric|min:0',
        ], [
            'editItems.*.product_id.required_if' => 'Please select a product for the line.',
            'editItems.*.description.required_if' => 'Please provide a description for the blank line.',
            'editItems.*.quantity.min' => 'Quantity must be at least 0.01.',
        ]);

        DB::beginTransaction();
        try {
            $quote = Quotation::findOrFail($this->selectedQuoteId);
            $quote->items()->delete();

            $subtotal = 0;
            foreach ($this->editItems as $item) {
                $subtotal += ((float)$item['quantity'] * (float)$item['unit_price']);
            }
            $totalAmount = $this->editTotal;

            $quote->update([
                'customer_id' => $this->editCustomerId,
                'valid_until' => $this->editValidUntil,
                'tax_amount' => $this->editCalculatedTax,
                'shipping_amount' => $this->editShippingAmount ?: 0,
                'total_amount' => $totalAmount,
                'notes' => $this->editNotes,
            ]);

            foreach ($this->editItems as $item) {
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
            $this->showEditModal = false;
            if ($this->showDetailsModal && $this->selectedQuoteId === $quote->id) {
                $this->viewQuote($quote->id);
            }
            $this->dispatch('toast', type: 'success', message:  'Sales Quotation updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', message:  'Error updating quotation: ' . $e->getMessage());
        }
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
            $this->showEditModal = false;
            $this->dispatch('toast', type: 'success', message:  'Sales Quotation deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', message:  'Error deleting quotation: ' . $e->getMessage());
        }
    }

    public function updateStatus($id, $status)
    {
        if (!auth()->user()->can('edit quotations')) { abort(403); }
        Quotation::findOrFail($id)->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message:  "Quotation status updated to {$status}.");
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
            // 1. Create SCM Sales Order (Commercial/financial ledger log)
            $salesOrder = SalesOrder::create([
                'customer_id' => $quote->customer_id,
                'status' => 'confirmed',
                'total_amount' => $quote->total_amount,
                'tax_amount' => $quote->tax_amount,
                'shipping_amount' => $quote->shipping_amount,
                'notes' => $quote->notes,
            ]);

            // 2. Create Sales Order items matching quote lines
            foreach($quote->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);
            }

            // 3. Automatically sweep warehouse bins and run physical stock reservations
            foreach($quote->items as $item) {
                if ($item->product_id) {
                    $binStock = \App\Models\BinProductStock::where('product_id', $item->product_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('quantity', 'desc')
                        ->first();

                    if ($binStock) {
                        $qtyToReserve = min($item->quantity, $binStock->quantity);

                        // Subtract physically from active bin stock
                        $binStock->quantity -= $qtyToReserve;
                        $binStock->save();

                        // Log stock movement transaction
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

            // 6. Mark Quotation as Accepted
            $quote->update(['status' => 'accepted']);

            DB::commit();
            if ($this->showDetailsModal && $this->selectedQuoteId === $quote->id) {
                $this->viewQuote($quote->id);
            }
            $this->dispatch('toast', type: 'success', message:  "Quotation approved! Auto-created Sales Order SO-{$salesOrder->id} with inventory stock reservations successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', message:  'Error converting quote: ' . $e->getMessage());
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
                <button type="button" wire:click="$toggle('showCreateModal')" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
                    + New Sales Quote
                </button>
            @endcan
        </div>
    </div>

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
                        @forelse($this->getQuotesList() as $q)
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
                                    <button type="button" wire:click="viewQuote({{ $q->id }})" class="text-slate-600 hover:text-slate-900">View</button>
                                    
                                    @can('edit quotations')
                                        @if($q->status !== 'accepted')
                                            <button type="button" wire:click="editQuote({{ $q->id }})" class="text-indigo-600 hover:text-indigo-800">Edit</button>
                                        @endif
                                    @endcan
                                    
                                    @if($q->status === 'draft')
                                        <button type="button" wire:click="updateStatus({{ $q->id }}, 'sent')" class="text-indigo-600 hover:text-indigo-800">Mark Sent</button>
                                    @endif
                                    @if($q->status === 'sent')
                                        <button type="button" wire:click="convertToSalesOrder({{ $q->id }})" class="text-emerald-600 hover:text-emerald-800">Accept & Convert</button>
                                        <button type="button" wire:click="updateStatus({{ $q->id }}, 'rejected')" class="text-rose-600 hover:text-rose-800">Reject</button>
                                    @endif
                                    @if($q->status === 'accepted')
                                        <span class="text-gray-400 italic">Converted to SO</span>
                                    @endif

                                    @can('delete quotations')
                                        <button type="button" onclick="confirm('Are you sure you want to permanently delete this quotation estimate?') && @this.deleteQuote({{ $q->id }})" class="text-rose-600 hover:text-rose-800">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No active customer quotations logged.</td>
                            </tr>
                        @endforelse
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
                     :class="{ 'ring-2 ring-indigo-500 bg-indigo-50/15 border-indigo-200 shadow-md scale-[1.01]': draggingOver }"
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
                                 wire:click="viewQuote({{ $quote->id }})"
                                 class="bg-white p-4 rounded-2xl border border-gray-150 shadow-sm relative group hover:shadow-md hover:border-indigo-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"
                            >
                                <div class="text-xs font-extrabold text-gray-900 leading-snug pr-4">{{ $quote->reference_no }}</div>
                                <div class="text-[10px] font-bold text-indigo-600 mt-0.5 truncate">{{ $quote->customer->name ?? 'N/A' }}</div>
                                @if($quote->customer && $quote->customer->company_name)
                                    <div class="text-[9px] text-gray-400 mt-0.5 truncate">{{ $quote->customer->company_name }}</div>
                                @endif
                                <div class="text-[9px] text-gray-400 mt-1 font-semibold">Valid until: {{ date('M d, Y', strtotime($quote->valid_until)) }}</div>

                                <!-- Items preview -->
                                <div class="mt-2 text-[9px] text-gray-455 border-t border-slate-100 pt-2 space-y-0.5 max-h-[60px] overflow-hidden font-medium">
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
                                            class="p-0.5 text-[8px] bg-slate-50 border-gray-200 text-gray-600 rounded focus:ring-0 focus:border-indigo-400"
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

    <!-- Create Quotation Modal -->
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showCreateModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Create Commercial Sales Quotation</h3>

                    <form wire:submit.prevent="saveQuote" class="space-y-6 text-xs font-semibold text-gray-700">
                        
                        <!-- Top Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Target Customer Client *" />
                                <select wire:model="customer_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                    <option value="">-- Choose Customer --</option>
                                    @foreach($this->getCustomersList() as $cust)
                                        <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Validity Until *" />
                                <input type="date" wire:model="valid_until" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs font-bold text-gray-700 cursor-pointer pt-6">
                                    <input type="checkbox" wire:model.live="apply_gst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                    <span>Apply GST ({{ $gst_percentage }}%)</span>
                                </label>
                                @if($apply_gst)
                                    <select wire:model.live="gst_type" class="block w-full rounded-xl border-gray-300 text-xs text-gray-700 mt-2">
                                        <option value="exclusive">Exclusive (+ to Subtotal)</option>
                                        <option value="inclusive">Inclusive (in Price)</option>
                                    </select>
                                @endif
                            </div>
                            <div>
                                <x-input-label value="Est. Shipping / Freight Cost ($)" />
                                <input type="number" step="0.01" wire:model.live="shipping_amount" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Memo Notes to Customer" />
                                <x-text-input type="text" wire:model="notes" class="mt-1 block w-full text-xs" placeholder="e.g. Terms include 50% deposit..." />
                            </div>
                        </div>

                        <!-- Item lines -->
                        <div class="border-t border-gray-150 pt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-gray-900">Line Items & Dynamic Quantities</h4>
                                <div class="flex gap-2">
                                    <button type="button" wire:click="addBlankLine" class="px-2.5 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-[10px] font-bold">+ Blank Custom Line</button>
                                    <button type="button" wire:click="addItemLine" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[10px] font-bold">+ Add Product Line</button>
                                </div>
                            </div>

                            <div class="space-y-3 max-h-[220px] overflow-y-auto pr-1">
                                @foreach($items as $idx => $item)
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end bg-slate-50/50 p-2.5 rounded-2xl border border-slate-100">
                                        <div class="col-span-2">
                                            @if($item['is_blank'])
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase text-amber-600">Custom Item / Service</label>
                                                <input type="text" wire:model.live="items.{{ $idx }}.description" class="mt-1 block w-full rounded-xl border-amber-300 bg-amber-50/30 text-xs text-gray-700 focus:border-amber-500 focus:ring-amber-500" placeholder="e.g. Labor Fees, Installation...">
                                            @else
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Product Item</label>
                                                <select wire:model.live="items.{{ $idx }}.product_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                                    <option value="">-- Choose Product --</option>
                                                    @foreach($this->getProductsList() as $p)
                                                        <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Quantity</label>
                                            <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.quantity" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                        </div>
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Price per unit ($)</label>
                                                <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.unit_price" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                            </div>
                                            @if(count($items) > 1)
                                                <button type="button" wire:click="removeItemLine({{ $idx }})" class="text-rose-500 hover:text-rose-700 font-bold text-base mt-4">&times;</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Calculations and Totals -->
                        <div class="border-t border-gray-150 pt-4 flex flex-col items-end gap-2 text-sm">
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Subtotal:</span>
                                <span class="font-extrabold text-gray-900 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($this->subtotal, 2) }}</span>
                            </div>
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Tax:</span>
                                <span class="font-extrabold text-gray-900 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($this->calculatedTax, 2) }}</span>
                            </div>
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Shipping:</span>
                                <span class="font-extrabold text-gray-900 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($shipping_amount ?: 0, 2) }}</span>
                            </div>
                            <div class="flex gap-8 border-t border-gray-200 pt-2 text-base">
                                <span class="text-gray-500 font-black uppercase">Final Total:</span>
                                <span class="font-black text-indigo-600 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($this->total, 2) }}</span>
                            </div>
                        </div>

                        <!-- Submit actions -->
                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save Quote</button>
                        </div>
                    </form>
                </div>
            </div>
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
                                    <button type="button" wire:click="editQuote({{ $selectedQuote->id }})" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
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
                                <p class="text-[9px] text-slate-450 mt-0.5">Valid Until: {{ date('M d, Y', strtotime($selectedQuote->valid_until)) }}</p>
                                
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
                                    <tr class="text-[9px] text-slate-450 uppercase font-black tracking-wider bg-slate-50">
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
                                                <div class="font-extrabold text-slate-900">{{ $itm->product->name ?? 'Unknown' }}</div>
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
                                    <span class="font-mono text-indigo-700">{{ setting('currency_symbol', '$') }}{{ number_format($selectedQuote->total_amount, 2) }}</span>
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
                                <span class="text-[9px] font-mono text-slate-500 block truncate mt-1 bg-slate-50 p-1.5 rounded border border-slate-100" title="Secure SHA-256 ERP Verification Hash">
                                    {{ hash('sha256', $selectedQuote->reference_no . '|' . $selectedQuote->total_amount . '|' . $selectedQuote->valid_until) }}
                                </span>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="border-b border-slate-400 h-8"></div>
                                <div class="flex justify-between text-[9px] text-slate-450 font-bold uppercase tracking-wider">
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
                                <button type="button" wire:click="updateStatus({{ $selectedQuote->id }}, 'sent')" class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition-colors">
                                    Mark Sent &amp; Dispatch Estimate
                                </button>
                            @endif
                            @if($selectedQuote->status === 'sent')
                                <button type="button" wire:click="convertToSalesOrder({{ $selectedQuote->id }})" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl transition-colors">
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
                                    class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl transition-colors"
                            >
                                Delete Quotation
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Quotation Edit Modal -->
    @if($showEditModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showEditModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Edit Commercial Sales Quotation</h3>

                    <form wire:submit.prevent="updateQuote" class="space-y-6 text-xs font-semibold text-gray-700">
                        
                        <!-- Top Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Target Customer Client *" />
                                <select wire:model="editCustomerId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                    <option value="">-- Choose Customer --</option>
                                    @foreach($this->getCustomersList() as $cust)
                                        <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Validity Until *" />
                                <input type="date" wire:model="editValidUntil" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs font-bold text-gray-700 cursor-pointer pt-6">
                                    <input type="checkbox" wire:model.live="editApplyGst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                    <span>Apply GST ({{ $gst_percentage }}%)</span>
                                </label>
                                @if($editApplyGst)
                                    <select wire:model.live="editGstType" class="block w-full rounded-xl border-gray-300 text-xs text-gray-700 mt-2">
                                        <option value="exclusive">Exclusive (+ to Subtotal)</option>
                                        <option value="inclusive">Inclusive (in Price)</option>
                                    </select>
                                @endif
                            </div>
                            <div>
                                <x-input-label value="Est. Shipping / Freight Cost ($)" />
                                <input type="number" step="0.01" wire:model.live="editShippingAmount" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Memo Notes to Customer" />
                                <x-text-input type="text" wire:model="editNotes" class="mt-1 block w-full text-xs" placeholder="e.g. Terms include 50% deposit..." />
                            </div>
                        </div>

                        <!-- Item lines -->
                        <div class="border-t border-gray-150 pt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-gray-900">Line Items & Dynamic Quantities</h4>
                                <div class="flex gap-2">
                                    <button type="button" wire:click="addEditBlankLine" class="px-2.5 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-[10px] font-bold">+ Blank Custom Line</button>
                                    <button type="button" wire:click="addEditItemLine" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[10px] font-bold">+ Add Product Line</button>
                                </div>
                            </div>

                            <div class="space-y-3 max-h-[220px] overflow-y-auto pr-1">
                                @foreach($editItems as $idx => $item)
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end bg-slate-50/50 p-2.5 rounded-2xl border border-slate-100">
                                        <div class="col-span-2">
                                            @if($item['is_blank'])
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase text-amber-600">Custom Item / Service</label>
                                                <input type="text" wire:model.live="editItems.{{ $idx }}.description" class="mt-1 block w-full rounded-xl border-amber-300 bg-amber-50/30 text-xs text-gray-700 focus:border-amber-500 focus:ring-amber-500" placeholder="e.g. Labor Fees, Installation...">
                                            @else
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Product Item</label>
                                                <select wire:model.live="editItems.{{ $idx }}.product_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                                    <option value="">-- Choose Product --</option>
                                                    @foreach($this->getProductsList() as $p)
                                                        <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Quantity</label>
                                            <input type="number" step="0.01" wire:model.live="editItems.{{ $idx }}.quantity" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                        </div>
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Price per unit ($)</label>
                                                <input type="number" step="0.01" wire:model.live="editItems.{{ $idx }}.unit_price" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                            </div>
                                            @if(count($editItems) > 1)
                                                <button type="button" wire:click="removeEditItemLine({{ $idx }})" class="text-rose-500 hover:text-rose-700 font-bold text-base mt-4">&times;</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Calculations and Totals -->
                        <div class="border-t border-gray-150 pt-4 flex flex-col items-end gap-2 text-sm">
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Subtotal:</span>
                                <span class="font-extrabold text-gray-900 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($this->editSubtotal, 2) }}</span>
                            </div>
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Tax:</span>
                                <span class="font-extrabold text-gray-900 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($this->editCalculatedTax, 2) }}</span>
                            </div>
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Shipping:</span>
                                <span class="font-extrabold text-gray-900 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($editShippingAmount ?: 0, 2) }}</span>
                            </div>
                            <div class="flex gap-8 border-t border-gray-200 pt-2 text-base">
                                <span class="text-gray-500 font-black uppercase">Final Total:</span>
                                <span class="font-black text-indigo-600 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($this->editTotal, 2) }}</span>
                            </div>
                        </div>

                        <!-- Submit actions -->
                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>


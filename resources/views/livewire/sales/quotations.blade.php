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
    // Form fields
    public $customer_id = '';
    public $crm_lead_id = null;
    public $valid_until = '';
    public $tax_amount = 0.00;
    public $shipping_amount = 0.00;
    public $notes = '';
    
    // Item lines (array of products and details)
    public $items = []; // array of ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00]
    
    public $showCreateModal = false;
    
    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->valid_until = now()->addDays(30)->toDateString();
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
        $this->items = [
            ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00]
        ];
    }

    public function addItemLine()
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00];
    }

    public function removeItemLine($index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function updatedItems($value, $key)
    {
        // If product_id of a row changes, automatically pull and populate its base unit price
        // Key format: "items.0.product_id" or "0.product_id"
        if (str_contains($key, '.product_id')) {
            $parts = explode('.', $key);
            $index = ($parts[0] === 'items') ? ($parts[1] ?? null) : ($parts[0] ?? null);
            
            if ($index !== null && isset($this->items[$index])) {
                $productId = $this->items[$index]['product_id'] ?? null;
                
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
            $sub += ($item['quantity'] * $item['unit_price']);
        }
        return $sub;
    }

    public function getTotalProperty()
    {
        return $this->subtotal + (float)$this->tax_amount + (float)$this->shipping_amount;
    }

    public function saveQuote()
    {
        $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'valid_until' => 'required|date',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ], [
            'items.*.product_id.required' => 'Please select a product for each line.',
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
                'tax_amount' => $this->tax_amount ?: 0,
                'shipping_amount' => $this->shipping_amount ?: 0,
                'notes' => $this->notes,
            ]);

            foreach($this->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();
            $this->showCreateModal = false;
            session()->flash('message', 'Draft Sales Quotation created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error creating quote: ' . $e->getMessage());
        }
    }

    public function updateStatus($id, $status)
    {
        Quotation::findOrFail($id)->update(['status' => $status]);
        session()->flash('message', "Quotation status updated to {$status}.");
    }

    public function convertToSalesOrder($id)
    {
        $quote = Quotation::with(['items', 'customer'])->findOrFail($id);

        DB::beginTransaction();
        try {
            // 1. Create SCM Sales Order (Commercial/financial ledger log)
            $salesOrder = SalesOrder::create([
                'customer_id' => $quote->customer_id,
                'status' => 'confirmed',
                'total_amount' => $quote->total_amount,
            ]);

            // 2. Create Sales Order items matching quote lines
            foreach($quote->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ]);
            }

            // 3. Automatically create SCM Project portfolio linked to Customer
            $projectCode = 'PRJ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $project = \App\Models\Project::create([
                'name' => 'Project: ' . ($quote->customer->company_name ?? $quote->customer->name ?? 'Client') . ' - Quote #' . $quote->reference_no,
                'code' => $projectCode,
                'customer_id' => $quote->customer_id,
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(90)->toDateString(),
                'budget' => $quote->total_amount,
                'description' => "Automatically generated execution project from Quotation " . $quote->reference_no . ". Notes: " . $quote->notes
            ]);

            // 4. Automatically create target milestones
            \App\Models\ProjectMilestone::create([
                'project_id' => $project->id,
                'title' => 'Milestone 1: Design & Engineering Kickoff',
                'description' => 'Verify specifications and align project requirements.',
                'due_date' => now()->addDays(15)->toDateString(),
                'status' => 'pending',
            ]);
            \App\Models\ProjectMilestone::create([
                'project_id' => $project->id,
                'title' => 'Milestone 2: SCM Material Sourcing & Picking',
                'description' => 'Reserve bin stocks and procure missing products.',
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'pending',
            ]);
            \App\Models\ProjectMilestone::create([
                'project_id' => $project->id,
                'title' => 'Milestone 3: SCM Core Execution & Testing',
                'description' => 'Assemble materials and execute client contract.',
                'due_date' => now()->addDays(60)->toDateString(),
                'status' => 'pending',
            ]);
            \App\Models\ProjectMilestone::create([
                'project_id' => $project->id,
                'title' => 'Milestone 4: Handover & Invoice Settlement',
                'description' => 'Client sign-off and final payment certificate issuance.',
                'due_date' => now()->addDays(90)->toDateString(),
                'status' => 'pending',
            ]);

            // 5. Automatically sweep warehouse bins and run physical stock reservations
            foreach($quote->items as $item) {
                $binStock = \App\Models\BinProductStock::where('product_id', $item->product_id)
                    ->where('quantity', '>', 0)
                    ->orderBy('quantity', 'desc')
                    ->first();

                if ($binStock) {
                    $qtyToReserve = min($item->quantity, $binStock->quantity);

                    // Subtract physically from active bin stock
                    $binStock->quantity -= $qtyToReserve;
                    $binStock->save();

                    // Create material request reservation record
                    \App\Models\ProjectMaterialRequest::create([
                        'project_id' => $project->id,
                        'product_id' => $item->product_id,
                        'warehouse_bin_id' => $binStock->warehouse_bin_id,
                        'quantity_requested' => $item->quantity,
                        'quantity_reserved' => $qtyToReserve,
                        'status' => $qtyToReserve >= $item->quantity ? 'reserved' : 'pending',
                        'notes' => 'Auto-allocated from approved Quote ' . $quote->reference_no,
                    ]);

                    // Log stock movement transaction
                    \App\Models\InventoryTransaction::create([
                        'product_id' => $item->product_id,
                        'from_bin_id' => $binStock->warehouse_bin_id,
                        'to_bin_id' => null,
                        'type' => 'OUT',
                        'quantity' => $qtyToReserve,
                        'reference_type' => 'project_reservation',
                        'reference_id' => $project->id,
                        'notes' => 'Auto-reserved from approved Quote ' . $quote->reference_no,
                        'user_id' => auth()->id(),
                    ]);
                } else {
                    // Out of stock, register pending request to alert procurement
                    \App\Models\ProjectMaterialRequest::create([
                        'project_id' => $project->id,
                        'product_id' => $item->product_id,
                        'warehouse_bin_id' => null,
                        'quantity_requested' => $item->quantity,
                        'quantity_reserved' => 0.00,
                        'status' => 'pending',
                        'notes' => 'Auto-created (Out of stock) from approved Quote ' . $quote->reference_no,
                    ]);
                }
            }

            // 6. Mark Quotation as Accepted
            $quote->update(['status' => 'accepted']);

            DB::commit();
            session()->flash('message', "Quotation approved! Auto-created Sales Order SO-{$salesOrder->id} & Project portfolio {$projectCode} with standard milestones and inventory stock reservations successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error converting quote to project: ' . $e->getMessage());
        }
    }
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">

    @if(session()->has('message'))
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm font-semibold text-sm">
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl shadow-sm font-semibold text-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Sales Quotation Management</h1>
            <p class="text-xs text-gray-500 mt-1">Generate dynamic customer pricing estimates, track pipeline approvals, and instantly convert accepted quotes into physical SCM orders.</p>
        </div>
        <button type="button" wire:click="$toggle('showCreateModal')" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
            + New Sales Quote
        </button>
    </div>

    <!-- Quotations Table -->
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
                                    <div class="truncate">{{ $itm->quantity }}x {{ $itm->product->name ?? 'Unknown' }}</div>
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
                            <div>
                                <x-input-label value="Estimated Tax Amount ($)" />
                                <input type="number" step="0.01" wire:model.live="tax_amount" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
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
                                <button type="button" wire:click="addItemLine" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[10px] font-bold">+ Add Row Line</button>
                            </div>

                            <div class="space-y-3 max-h-[220px] overflow-y-auto pr-1">
                                @foreach($items as $idx => $item)
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end bg-slate-50/50 p-2.5 rounded-2xl border border-slate-100">
                                        <div class="col-span-2">
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Product Item</label>
                                            <select wire:model.live="items.{{ $idx }}.product_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                                <option value="">-- Choose Product --</option>
                                                @foreach($this->getProductsList() as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                @endforeach
                                            </select>
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
                                <span class="font-extrabold text-gray-900 font-mono">${{ number_format($this->subtotal, 2) }}</span>
                            </div>
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Tax:</span>
                                <span class="font-extrabold text-gray-900 font-mono">${{ number_format($tax_amount ?: 0, 2) }}</span>
                            </div>
                            <div class="flex gap-8">
                                <span class="text-gray-400 font-bold uppercase">Shipping:</span>
                                <span class="font-extrabold text-gray-900 font-mono">${{ number_format($shipping_amount ?: 0, 2) }}</span>
                            </div>
                            <div class="flex gap-8 border-t border-gray-200 pt-2 text-base">
                                <span class="text-gray-500 font-black uppercase">Final Total:</span>
                                <span class="font-black text-indigo-600 font-mono">${{ number_format($this->total, 2) }}</span>
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

</div>

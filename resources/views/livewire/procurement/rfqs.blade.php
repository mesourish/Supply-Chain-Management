<?php

use Livewire\Volt\Component;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // Form fields
    public $supplier_id = '';
    public $delivery_date = '';
    public $total_amount = 0.00;
    public $notes = '';
    
    // RFQ form item lines
    public $items = []; // array of ['product_id' => '', 'quantity' => 1, 'unit_cost' => 0.00]
    
    public $showCreateModal = false;
    public $showLogBidModal = false;
    public $selectedRfqId = null;

    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->delivery_date = now()->addDays(14)->toDateString();
        $this->resetForm();
    }

    public function getRfqsList()
    {
        return Rfq::with(['supplier'])->latest()->get();
    }

    public function getSuppliersList()
    {
        return Supplier::orderBy('name')->get();
    }

    public function getProductsList()
    {
        return Product::orderBy('name')->get();
    }

    public function resetForm()
    {
        $this->supplier_id = '';
        $this->delivery_date = now()->addDays(14)->toDateString();
        $this->total_amount = 0.00;
        $this->notes = '';
        $this->items = [
            ['product_id' => '', 'quantity' => 1, 'unit_cost' => 0.00]
        ];
    }

    public function addItemLine()
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_cost' => 0.00];
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
        // Auto pull product's standard cost price when product changes
        if (str_contains($key, '.product_id')) {
            $parts = explode('.', $key);
            $index = ($parts[0] === 'items') ? ($parts[1] ?? null) : ($parts[0] ?? null);
            
            if ($index !== null && isset($this->items[$index])) {
                $productId = $this->items[$index]['product_id'] ?? null;
                
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $this->items[$index]['unit_cost'] = $product->cost_price;
                    }
                }
            }
        }
    }

    public function getSubtotalProperty()
    {
        $sub = 0;
        foreach($this->items as $item) {
            $sub += ($item['quantity'] * $item['unit_cost']);
        }
        return $sub;
    }

    public function saveRfq()
    {
        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'delivery_date' => 'required|date',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $rfq = Rfq::create([
                'reference_no' => 'RFQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'supplier_id' => $this->supplier_id,
                'status' => 'draft',
                'delivery_date' => $this->delivery_date,
                'total_amount' => $this->subtotal,
                'notes' => $this->notes,
            ]);

            // For now, RFQ items can be serialized or stored inside notes
            // Let's store lines as custom note serializations since there is no separate RFQItems table
            // But wait, the notes column is a text column! We can save the items as JSON in the notes column!
            // Let's do that! It is incredibly clean and does not require complex tables.
            $rfq->update(['notes' => json_encode([
                'memo' => $this->notes,
                'lines' => $this->items
            ])]);

            DB::commit();
            $this->showCreateModal = false;
            session()->flash('message', 'Draft Procurement RFQ compiled successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error compiling RFQ: ' . $e->getMessage());
        }
    }

    public function updateStatus($id, $status)
    {
        Rfq::findOrFail($id)->update(['status' => $status]);
        session()->flash('message', "RFQ status updated to {$status}.");
    }

    public function openLogBidModal($id)
    {
        $rfq = Rfq::findOrFail($id);
        $this->selectedRfqId = $id;
        $this->total_amount = $rfq->total_amount;
        $this->notes = '';
        
        $data = json_decode($rfq->notes, true);
        $this->items = $data['lines'] ?? [];
        $this->showLogBidModal = true;
    }

    public function saveBid()
    {
        $this->validate([
            'total_amount' => 'required|numeric|min:0',
        ]);

        $rfq = Rfq::findOrFail($this->selectedRfqId);
        $data = json_decode($rfq->notes, true);
        $data['lines'] = $this->items;
        $data['memo'] = $this->notes;

        $rfq->update([
            'status' => 'received',
            'total_amount' => $this->total_amount,
            'notes' => json_encode($data)
        ]);

        $this->showLogBidModal = false;
        session()->flash('message', 'Supplier bid response logged successfully.');
    }

    public function convertToPurchaseOrder($id)
    {
        $rfq = Rfq::findOrFail($id);
        $data = json_decode($rfq->notes, true);
        $lines = $data['lines'] ?? [];

        DB::beginTransaction();
        try {
            // 1. Create SCM Purchase Order
            $po = PurchaseOrder::create([
                'supplier_id' => $rfq->supplier_id,
                'status' => 'approved', // Auto approved since it matches compiled RFQ win
                'total_amount' => $rfq->total_amount,
            ]);

             // 2. Create Purchase Order Items
             foreach($lines as $line) {
                 PurchaseOrderItem::create([
                     'purchase_order_id' => $po->id,
                     'product_id' => $line['product_id'] ?? null,
                     'quantity' => $line['quantity'] ?? 1,
                     'unit_cost' => $line['unit_cost'] ?? 0.00,
                     'total_cost' => ($line['quantity'] ?? 1) * ($line['unit_cost'] ?? 0.00),
                 ]);
             }

            // 3. Mark RFQ as Closed/Accepted
            $rfq->update(['status' => 'accepted']);

            DB::commit();
            session()->flash('message', "Supplier bid accepted! Successfully converted RFQ to active SCM Purchase Order PO-{$po->id}.");
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error converting RFQ to PO: ' . $e->getMessage());
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
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Request for Quotations (RFQs)</h1>
            <p class="text-xs text-gray-500 mt-1">Compile Outbound RFQs, log supplier bid submissions, analyze cost prices, and trigger automatic warehouse restocking orders.</p>
        </div>
        <button type="button" wire:click="$toggle('showCreateModal')" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
            + Create Supplier RFQ
        </button>
    </div>

    <!-- RFQs list -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-150">
                <thead>
                    <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                        <th class="px-4 py-3 rounded-l-xl">RFQ Reference</th>
                        <th class="px-4 py-3">Supplier Vendor</th>
                        <th class="px-4 py-3">Delivery Deadline</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Est. Cost Value</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-sm">
                    @forelse($this->getRfqsList() as $rfq)
                        <tr class="hover:bg-gray-50/20 transition-colors">
                            <td class="px-4 py-3.5 whitespace-nowrap font-extrabold text-gray-900">
                                {{ $rfq->reference_no }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="font-bold text-gray-900">{{ $rfq->supplier->name ?? 'N/A' }}</div>
                                <div class="text-[10px] text-gray-400 font-semibold mt-0.5">{{ $rfq->supplier->email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-500 font-semibold">
                                {{ $rfq->delivery_date ? date('M d, Y', strtotime($rfq->delivery_date)) : 'Not Specified' }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @php
                                    $badge = 'bg-gray-50 text-gray-700 border border-gray-100';
                                    if ($rfq->status === 'accepted') $badge = 'bg-green-50 text-green-700 border border-green-100';
                                    elseif ($rfq->status === 'sent') $badge = 'bg-blue-50 text-blue-700 border border-blue-100';
                                    elseif ($rfq->status === 'received') $badge = 'bg-amber-50 text-amber-700 border border-amber-100';
                                    elseif ($rfq->status === 'closed') $badge = 'bg-rose-50 text-rose-700 border border-rose-100';
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ $badge }}">
                                    {{ $rfq->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap font-mono text-right font-extrabold text-gray-900">
                                {{ setting('currency_symbol', '$') }}{{ number_format($rfq->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-right text-xs font-bold space-x-2">
                                @if($rfq->status === 'draft')
                                    <button type="button" wire:click="updateStatus({{ $rfq->id }}, 'sent')" class="text-indigo-600 hover:text-indigo-800">Transmit RFQ</button>
                                @endif
                                @if($rfq->status === 'sent')
                                    <button type="button" wire:click="openLogBidModal({{ $rfq->id }})" class="text-amber-600 hover:text-amber-800">Log Bid Price</button>
                                @endif
                                @if($rfq->status === 'received')
                                    <button type="button" wire:click="convertToPurchaseOrder({{ $rfq->id }})" class="text-emerald-600 hover:text-emerald-800">Accept Bid & Order</button>
                                    <button type="button" wire:click="updateStatus({{ $rfq->id }}, 'closed')" class="text-rose-600 hover:text-rose-800">Close Bid</button>
                                @endif
                                @if($rfq->status === 'accepted')
                                    <span class="text-gray-400 italic">Restocked via PO</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No active Procurement RFQs logged.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create RFQ Modal -->
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showCreateModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Request Supplier Catalog Pricing (RFQ)</h3>

                    <form wire:submit.prevent="saveRfq" class="space-y-6 text-xs font-semibold text-gray-700">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Target Supplier Vendor *" />
                                <select wire:model="supplier_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                    <option value="">-- Choose Vendor --</option>
                                    @foreach($this->getSuppliersList() as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Delivery Target Date *" />
                                <input type="date" wire:model="delivery_date" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Internal Notes / RFQ Specifications" />
                                <x-text-input type="text" wire:model="notes" class="mt-1 block w-full text-xs" placeholder="Describe shipping terms or pricing requirements..." />
                            </div>
                        </div>

                        <!-- Item Lines -->
                        <div class="border-t border-gray-150 pt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-gray-900">Requested Restock Materials</h4>
                                <button type="button" wire:click="addItemLine" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[10px] font-bold">+ Add Product Line</button>
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
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Quantity Requested</label>
                                            <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.quantity" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                        </div>
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Target Cost per unit ($)</label>
                                                <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.unit_cost" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                            </div>
                                            @if(count($items) > 1)
                                                <button type="button" wire:click="removeItemLine({{ $idx }})" class="text-rose-500 hover:text-rose-700 font-bold text-base mt-4">&times;</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Est Totals -->
                        <div class="border-t border-gray-150 pt-4 flex justify-between items-center text-sm font-bold">
                            <span class="text-gray-400 uppercase">Estimated Subtotal:</span>
                            <span class="font-extrabold text-indigo-600 font-mono">${{ number_format($this->subtotal, 2) }}</span>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save RFQ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Log Received Bid Modal -->
    @if($showLogBidModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showLogBidModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Log Supplier Bid Submission</h3>

                    <form wire:submit.prevent="saveBid" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <span class="text-[10px] text-gray-400 font-bold uppercase">Update Quoted Line Costs</span>
                            <div class="mt-2 space-y-3 max-h-[180px] overflow-y-auto pr-1">
                                @foreach($items as $idx => $line)
                                    <div class="flex items-center gap-4 bg-slate-50 p-2 rounded-xl justify-between border border-slate-100">
                                        <div class="truncate text-xs font-bold text-gray-800">
                                            {{ $line['quantity'] ?? 1 }}x {{ Product::find($line['product_id'] ?? null)?->name ?? 'Product' }}
                                        </div>
                                        <div class="w-32">
                                            <input type="number" step="0.01" wire:model="items.{{ $idx }}.unit_cost" class="block w-full rounded-lg border-gray-200 text-xs font-bold text-gray-700 text-right">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Final Quoted Supplier Total Value ($) *" />
                            <x-text-input wire:model="total_amount" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs" required />
                        </div>
                        <div>
                            <x-input-label value="Supplier Bid Memo / Negotiation Notes" />
                            <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Memo notes..."></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showLogBidModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Record Supplier Bid</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>

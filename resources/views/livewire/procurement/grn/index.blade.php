<?php

use function Livewire\Volt\{state, mount};
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryTransaction;
use App\Models\AccountPayable;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;

state([
    'purchaseOrders' => [],
    'grns' => [],
    
    'showModal' => false,
    'selectedPo' => null,
    'notes' => '',
    'bin_id' => '',
    'bins' => [],
    'receive_quantities' => [],
    'showViewModal' => false,
    'viewGrnDetails' => null,
]);

$viewGrn = function ($id) {
    $this->viewGrnDetails = GoodsReceiptNote::with(['purchaseOrder.supplier', 'user'])->findOrFail($id);
    $this->showViewModal = true;
};

mount(function () {
    if (!auth()->user()->can('view purchase_orders') && !auth()->user()->can('view grn')) {
        abort(403);
    }
    $this->loadData();
});

$loadData = function () {
    // POs that are approved or partially received
    $this->purchaseOrders = PurchaseOrder::with(['supplier', 'items.product'])
        ->whereIn('status', ['approved', 'draft', 'partially_received'])
        ->latest()
        ->get();
        
    $this->grns = GoodsReceiptNote::with(['purchaseOrder.supplier', 'user'])
        ->latest()
        ->get();
        
    $this->bins = WarehouseBin::with('warehouse')->get();
};

$receive = function (PurchaseOrder $po) {
    if (!auth()->user()->can('receive purchase_orders') && !auth()->user()->can('create grn')) abort(403);
    $this->selectedPo = $po;
    $this->notes = '';
    $this->bin_id = $this->bins->first()->id ?? null;
    
    $receiveQuantities = [];
    foreach ($po->items as $item) {
        $remaining = $item->quantity - $item->received_quantity;
        $receiveQuantities[$item->id] = $remaining > 0 ? $remaining : 0;
    }
    $this->receive_quantities = $receiveQuantities;
    
    $this->showModal = true;
};

$confirmReceipt = function () {
    if (!auth()->user()->can('receive purchase_orders') && !auth()->user()->can('create grn')) abort(403);
    
    $this->validate([
        'bin_id' => 'required|exists:warehouse_bins,id',
    ], [
        'bin_id.required' => 'You must select a warehouse bin to receive items into.'
    ]);

    DB::transaction(function () {
        // Filter out items that are not being received
        $itemsToReceive = [];
        $totalAmountReceived = 0;
        
        foreach ($this->selectedPo->items as $item) {
            $qty = intval($this->receive_quantities[$item->id] ?? 0);
            if ($qty > 0) {
                $remaining = $item->quantity - $item->received_quantity;
                if ($qty > $remaining) {
                    $qty = $remaining; // Cap at remaining quantity
                }
                $itemsToReceive[] = ['item' => $item, 'qty' => $qty];
                $totalAmountReceived += ($qty * $item->unit_price);
            }
        }
        
        if (empty($itemsToReceive)) {
            session()->flash('error', 'No items selected to receive.');
            return;
        }

        // 1. Create GRN
        $grn = GoodsReceiptNote::create([
            'purchase_order_id' => $this->selectedPo->id,
            'user_id' => auth()->id(),
            'status' => 'received',
            'notes' => $this->notes,
        ]);

        $po = PurchaseOrder::find($this->selectedPo->id);
        $allFullyReceived = true;

        // 3. Create Inventory Transactions (IN) and Update Received Quantity
        foreach ($itemsToReceive as $record) {
            $item = $record['item'];
            $qty = $record['qty'];
            
            $item->increment('received_quantity', $qty);
            
            InventoryTransaction::create([
                'product_id' => $item->product_id,
                'from_bin_id' => null,
                'to_bin_id' => $this->bin_id,
                'type' => 'IN',
                'quantity' => $qty,
                'reference_type' => GoodsReceiptNote::class,
                'reference_id' => $grn->id,
                'notes' => 'Received via GRN #' . $grn->id,
                'user_id' => auth()->id(),
            ]);
            
            if ($item->received_quantity < $item->quantity) {
                $allFullyReceived = false;
            }
        }
        
        // Re-check all items just to be sure
        foreach ($po->items()->get() as $item) {
            if ($item->received_quantity < $item->quantity) {
                $allFullyReceived = false;
            }
        }

        // 2. Update PO status
        if ($allFullyReceived) {
            $po->update(['status' => 'received']);
        } else {
            $po->update(['status' => 'partially_received']);
        }

        // 4. Create Account Payable (Pro-rated for this GRN)
        if ($totalAmountReceived > 0) {
            AccountPayable::create([
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'amount' => $totalAmountReceived,
                'status' => 'unpaid',
            ]);
        }
        
        session()->flash('success', 'GRN successfully created for received items.');
    });

    $this->showModal = false;
    $this->selectedPo = null;
    $this->loadData();
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Goods Receipt Notes (GRN)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            @if (session()->has('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif
            
            @if (session()->has('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Pending POs -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    <h3 class="text-lg font-bold mb-4">Pending Purchase Orders</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items Received</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($purchaseOrders as $po)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">PO-#{{ $po->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $po->supplier->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ setting('currency_symbol', '$') }}{{ number_format($po->total_amount, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $po->items->sum('received_quantity') }} / {{ $po->items->sum('quantity') }} units
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($po->status === 'partially_received')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Partially Received</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @if(auth()->user()->can('receive purchase_orders') || auth()->user()->can('create grn'))
                                            <button wire:click="receive({{ $po->id }})" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">Receive Goods</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No pending purchase orders to receive.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Historical GRNs -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4">GRN History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">GRN ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Received By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($grns as $grn)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">GRN-#{{ $grn->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">PO-#{{ $grn->purchase_order_id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grn->purchaseOrder->supplier->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grn->user->name ?? 'Unknown' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grn->created_at->format('M d, Y H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button wire:click="viewGrn({{ $grn->id }})" class="text-indigo-600 hover:text-indigo-900">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No goods receipts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Receive Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="$set('showModal', false)"></div>

            <div class="inline-block w-full max-w-3xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg relative z-50">
                @if($selectedPo)
                <h3 class="text-lg font-bold leading-6 text-gray-900 mb-4 border-b pb-2">
                    Receive PO-#{{ $selectedPo->id }} ({{ $selectedPo->supplier->name }})
                </h3>
                
                <form wire:submit="confirmReceipt">
                    <div class="mb-4">
                        <h4 class="font-medium text-sm text-gray-700 mb-2">Items to Receive:</h4>
                        <div class="overflow-hidden border border-gray-200 rounded-md">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Product</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Ordered</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Already Received</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Receive Now</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($selectedPo->items as $item)
                                        @php
                                            $remaining = $item->quantity - $item->received_quantity;
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">{{ $item->product->name }}</td>
                                            <td class="px-4 py-3">{{ $item->quantity }}</td>
                                            <td class="px-4 py-3 text-green-600">{{ $item->received_quantity }}</td>
                                            <td class="px-4 py-3">
                                                @if($remaining > 0)
                                                    <input type="number" min="0" max="{{ $remaining }}" wire:model="receive_quantities.{{ $item->id }}" class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                @else
                                                    <span class="text-gray-400">Fully Received</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Receive into Warehouse Bin (For all items)</label>
                        <select wire:model="bin_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select a bin...</option>
                            @foreach($bins as $bin)
                                <option value="{{ $bin->id }}">{{ $bin->warehouse->name }} - {{ $bin->full_label }}</option>
                            @endforeach
                        </select>
                        @error('bin_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Any condition notes or discrepancies..."></textarea>
                    </div>

                    <div class="mt-5 sm:mt-6 flex justify-end gap-3 border-t pt-4">
                        <button type="button" @click="show = false" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:text-sm">
                            Confirm Receipt & Generate AP
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- View GRN Modal -->
    @if($showViewModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="$set('showViewModal', false)"></div>
            <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg relative z-50">
                @if($viewGrnDetails)
                <div class="flex justify-between items-center mb-4 border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">GRN Details (GRN-#{{ $viewGrnDetails->id }})</h3>
                    <button wire:click="$set('showViewModal', false)" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                    <div>
                        <p><span class="font-medium text-gray-500">PO Reference:</span> PO-#{{ $viewGrnDetails->purchase_order_id }}</p>
                        <p><span class="font-medium text-gray-500">Supplier:</span> {{ $viewGrnDetails->purchaseOrder->supplier->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p><span class="font-medium text-gray-500">Received By:</span> {{ $viewGrnDetails->user->name ?? 'Unknown' }}</p>
                        <p><span class="font-medium text-gray-500">Date:</span> {{ $viewGrnDetails->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    @if($viewGrnDetails->notes)
                    <div class="col-span-2 mt-2">
                        <p><span class="font-medium text-gray-500">Notes:</span> {{ $viewGrnDetails->notes }}</p>
                    </div>
                    @endif
                </div>

                <h4 class="font-semibold text-gray-700 mb-2">Items Received</h4>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase">Bin</th>
                                <th class="text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse(\App\Models\InventoryTransaction::where('reference_type', \App\Models\GoodsReceiptNote::class)->where('reference_id', $viewGrnDetails->id)->with(['product', 'toBin'])->get() as $tx)
                                <tr>
                                    <td class="py-2 text-sm text-gray-900">{{ $tx->product->name ?? 'Unknown' }}</td>
                                    <td class="py-2 text-sm text-gray-500">{{ $tx->product->sku ?? 'Unknown' }}</td>
                                    <td class="py-2 text-sm text-gray-500">{{ $tx->toBin->code ?? 'N/A' }}</td>
                                    <td class="py-2 text-sm font-medium text-green-600 text-right">+{{ $tx->quantity }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-sm text-gray-500">No items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button wire:click="$set('showViewModal', false)" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Close</button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

<?php

use function Livewire\Volt\{state, mount};
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryTransaction;
use App\Models\AccountPayable;
use App\Models\WarehouseBin;
use App\Models\BinProductStock;
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
    'linkedMos' => [],
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
        ->whereIn('status', ['approved', 'partially_received'])
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
    
    // Connect with active MOs component shortages check
    $poProductIds = $po->items->pluck('product_id')->toArray();
    $activeMos = \App\Models\ManufacturingOrder::whereIn('status', ['draft', 'confirmed', 'in_progress'])
        ->whereHas('billOfMaterial.items', function ($query) use ($poProductIds) {
            $query->whereIn('component_product_id', $poProductIds);
        })
        ->with(['product', 'billOfMaterial.items.product'])
        ->get();

    $linked = [];
    foreach ($activeMos as $mo) {
        $bom = $mo->billOfMaterial;
        $moComponents = [];
        $hasShortage = false;
        
        foreach ($bom->items as $bomItem) {
            if (in_array($bomItem->component_product_id, $poProductIds)) {
                $qtyRequired = ($bomItem->quantity_required * $mo->quantity_to_produce) / $bom->output_quantity;
                $currentStock = \App\Models\BinProductStock::where('product_id', $bomItem->component_product_id)->sum('quantity');
                $shortage = $qtyRequired - $currentStock;
                
                if ($shortage > 0) {
                    $hasShortage = true;
                    $poItem = $po->items->firstWhere('product_id', $bomItem->component_product_id);
                    $receivingNow = intval($receiveQuantities[$poItem->id] ?? 0);
                    
                    $moComponents[] = [
                        'product_id' => $bomItem->component_product_id,
                        'name' => $bomItem->product->name,
                        'sku' => $bomItem->product->sku,
                        'required' => $qtyRequired,
                        'on_hand' => $currentStock,
                        'shortage' => $shortage,
                        'will_satisfy' => ($receivingNow >= $shortage),
                    ];
                }
            }
        }
        
        if ($hasShortage && !empty($moComponents)) {
            $linked[] = [
                'id' => $mo->id,
                'mo_number' => $mo->mo_number,
                'finished_good' => $mo->product->name,
                'finished_sku' => $mo->product->sku,
                'status' => $mo->status,
                'components' => $moComponents,
            ];
        }
    }
    $this->linkedMos = $linked;
    $this->showModal = true;
};

$updatedReceiveQuantities = function ($value, $key) {
    if (!$this->selectedPo) return;
    
    $linked = $this->linkedMos;
    foreach ($linked as &$linkedMo) {
        foreach ($linkedMo['components'] as &$comp) {
            $poItem = $this->selectedPo->items->firstWhere('product_id', $comp['product_id']);
            if ($poItem) {
                $receivingNow = intval($this->receive_quantities[$poItem->id] ?? 0);
                $comp['will_satisfy'] = ($receivingNow >= $comp['shortage']);
            }
        }
    }
    $this->linkedMos = $linked;
};

$closeModal = function () {
    $this->showModal = false;
    $this->selectedPo = null;
    $this->linkedMos = [];
    $this->clearValidation();
};

$confirmReceipt = function () {
    if (!auth()->user()->can('receive purchase_orders') && !auth()->user()->can('create grn')) abort(403);
    
    $this->validate([
        'bin_id' => 'required|exists:warehouse_bins,id',
    ], [
        'bin_id.required' => 'You must select a warehouse bin to receive items into.'
    ]);

    $poProductIds = $this->selectedPo->items->pluck('product_id')->toArray();

    DB::transaction(function () use ($poProductIds) {
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
            $this->dispatch('toast', type: 'error', message:  'No items selected to receive.');
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

            // Update Physical Bin Stock
            $binStock = BinProductStock::firstOrCreate(
                ['warehouse_bin_id' => $this->bin_id, 'product_id' => $item->product_id],
                ['quantity' => 0, 'unit_cost' => $item->unit_price]
            );
            $binStock->increment('quantity', $qty);
            
            // Create pending quality check
            \App\Helpers\QualityControlHelper::createGrnCheck($grn, $item->product_id);

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

        // 5. Connect and Check Manufacturing Orders with resolved shortages
        $activeMosToCheck = \App\Models\ManufacturingOrder::whereIn('status', ['draft', 'confirmed', 'in_progress'])
            ->with(['product', 'billOfMaterial.items.product'])
            ->get();
            
        foreach ($activeMosToCheck as $mo) {
            $bom = $mo->billOfMaterial;
            $allSufficientNow = true;
            $usesReceivedItems = false;
            
            foreach ($bom->items as $bomItem) {
                if (in_array($bomItem->component_product_id, $poProductIds)) {
                    $usesReceivedItems = true;
                }
            }
            
            if (!$usesReceivedItems) continue;
            
            // Check if there was a shortage before receiving this GRN
            $hadShortageBefore = false;
            foreach ($bom->items as $bomItem) {
                $qtyRequired = ($bomItem->quantity_required * $mo->quantity_to_produce) / $bom->output_quantity;
                $currentStock = \App\Models\BinProductStock::where('product_id', $bomItem->component_product_id)->sum('quantity');
                
                $qtyReceivedThisGrn = 0;
                $poItem = $this->selectedPo->items->firstWhere('product_id', $bomItem->component_product_id);
                if ($poItem) {
                    $qtyReceivedThisGrn = intval($this->receive_quantities[$poItem->id] ?? 0);
                }
                
                $stockBefore = $currentStock - $qtyReceivedThisGrn;
                if ($stockBefore < $qtyRequired) {
                    $hadShortageBefore = true;
                }
                
                if ($currentStock < $qtyRequired) {
                    $allSufficientNow = false;
                }
            }
            
            if ($hadShortageBefore && $allSufficientNow) {
                session()->push('resolved_mo_messages', "Stock received satisfies all shortages for Manufacturing Order {$mo->mo_number}. This order is now ready for production.");
            }
        }
        
        session()->flash('success', 'GRN successfully created for received items.');
    });

    $this->showModal = false;
    $this->selectedPo = null;
    $this->linkedMos = [];
    $this->loadData();
    
    // Dispatch resolved MO toasts
    $messages = session()->pull('resolved_mo_messages', []);
    foreach ($messages as $msg) {
        $this->dispatch('toast', type: 'success', message: $msg);
    }
};

?>

<div class="p-6">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Premium Header Banner -->
        <div class="relative rounded-3xl overflow-hidden shadow-xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 p-8 border border-indigo-200/10 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="absolute inset-0 bg-radial-gradient from-indigo-500/10 via-transparent to-transparent pointer-events-none"></div>
            <div class="z-10 text-center md:text-left">
                <span class="px-3 py-1 text-xs font-semibold bg-indigo-500/20 text-indigo-300 rounded-full border border-indigo-500/30 uppercase tracking-widest">Procurement & Sourcing</span>
                <h1 class="text-4xl font-extrabold text-white mt-3 tracking-tight">Goods Receipt Notes (GRN)</h1>
                <p class="text-indigo-200/70 text-sm mt-1 max-w-xl">Inspect inbound vendor deliveries, execute physical stock intake into warehouse bins, and reconcile ledger accounts.</p>
            </div>
        </div>

        @if (session()->has('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl shadow-sm font-semibold text-sm flex items-center gap-2">
                <span class="text-emerald-500">✓</span> {{ session('success') }}
            </div>
        @endif

        <!-- Redesigned Pending Purchase Orders Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Pending Purchase Orders</h3>
                <span class="px-2.5 py-0.5 text-xs font-bold bg-slate-100 text-slate-500 rounded-md">{{ count($purchaseOrders) }} Pending POs</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($purchaseOrders as $po)
                    @php
                        $receivedUnits = $po->items->sum('received_quantity');
                        $totalUnits = $po->items->sum('quantity');
                        $progressPct = $totalUnits > 0 ? min(100, round(($receivedUnits / $totalUnits) * 100)) : 0;
                    @endphp
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between overflow-hidden">
                        <!-- Card Header -->
                        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-start">
                            <div>
                                <span class="text-xs font-bold text-indigo-600 block">PO-#{{ $po->id }}</span>
                                <span class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $po->created_at->format('M d, Y') }}</span>
                            </div>
                            @if($po->status === 'partially_received')
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-100">Partially Received</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">Approved</span>
                            @endif
                        </div>
                        
                        <!-- Card Body -->
                        <div class="p-5 flex-1 space-y-4">
                            <div>
                                <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Supplier Partner</span>
                                <span class="text-sm font-semibold text-slate-800">{{ $po->supplier->name }}</span>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="space-y-1.5">
                                <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                    <span>Intake Progress</span>
                                    <span>{{ $receivedUnits }} / {{ $totalUnits }} units ({{ $progressPct }}%)</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-indigo-600 h-full rounded-full transition-all" style="width: {{ $progressPct }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="p-5 pt-0 border-t border-slate-50 bg-slate-50/20 flex justify-between items-center gap-3 mt-auto">
                            <div>
                                <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Total Amount</span>
                                <span class="text-base font-black text-slate-800 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($po->total_amount, 2) }}</span>
                            </div>
                            <button wire:click="receive({{ $po->id }})" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-600/10 hover:shadow-emerald-600/25 transition">
                                Receive Goods
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white border border-slate-200 rounded-2xl p-12 text-center text-slate-400">
                        <span class="text-3xl block mb-2">🎉</span>
                        <h4 class="text-sm font-bold text-slate-700">All caught up!</h4>
                        <p class="text-xs text-slate-400 mt-1">No pending purchase orders waiting for inventory receipt.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Redesigned GRN History Section -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden space-y-4 p-6">
            <h3 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">GRN Receipt Ledger</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-slate-500 font-semibold">
                            <th class="px-6 py-4 text-left">GRN Reference</th>
                            <th class="px-6 py-4 text-left">PO Reference</th>
                            <th class="px-6 py-4 text-left">Supplier Partner</th>
                            <th class="px-6 py-4 text-left">Receiver</th>
                            <th class="px-6 py-4 text-left">Intake Date</th>
                            <th class="px-6 py-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($grns as $grn)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-bold text-slate-800">GRN-#{{ str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 text-slate-650 font-bold">PO-#{{ $grn->purchase_order_id }}</td>
                                <td class="px-6 py-4 text-slate-600 font-semibold">{{ $grn->purchaseOrder->supplier->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-slate-500">{{ $grn->user->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 text-slate-400 font-mono">{{ $grn->created_at->format(setting('date_format', 'Y-m-d') . ' ' . setting('time_format', 'H:i')) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button wire:click="viewGrn({{ $grn->id }})" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold rounded-xl text-xs transition">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-400 italic">No historical goods receipts logged.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Redesigned Receive Modal (with Manufacturing Connections) -->
    @if($showModal && $selectedPo)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl relative z-50">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Execute Inbound Cargo Intake</h3>
                        <p class="text-xs text-slate-400 mt-0.5">PO-#{{ $selectedPo->id }} &bull; Supplier: {{ $selectedPo->supplier->name }}</p>
                    </div>
                    <button wire:click="closeModal" class="text-slate-400 hover:text-slate-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form wire:submit="confirmReceipt" class="space-y-6">
                    <div>
                        <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Quantities Reconciliation</h4>
                        <div class="overflow-hidden border border-slate-200 rounded-xl">
                            <table class="min-w-full divide-y divide-slate-100 text-xs text-slate-650">
                                <thead class="bg-slate-50">
                                    <tr class="font-bold text-slate-500">
                                        <th class="px-4 py-2.5 text-left">Product</th>
                                        <th class="px-4 py-2.5 text-center">Ordered</th>
                                        <th class="px-4 py-2.5 text-center">Previously Received</th>
                                        <th class="px-4 py-2.5 text-center">Receive Now</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($selectedPo->items as $item)
                                        @php
                                            $remaining = $item->quantity - $item->received_quantity;
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="font-bold text-slate-800">{{ $item->product->name }}</div>
                                                <div class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ $item->product->sku }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-center font-bold font-mono">{{ $item->quantity }}</td>
                                            <td class="px-4 py-3 text-center font-bold text-emerald-600 font-mono">{{ $item->received_quantity }}</td>
                                            <td class="px-4 py-3 text-center">
                                                @if($remaining > 0)
                                                    <input type="number" min="0" max="{{ $remaining }}" 
                                                           wire:model.live="receive_quantities.{{ $item->id }}" 
                                                           class="w-24 text-center rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono font-bold">
                                                @else
                                                    <span class="text-xs font-bold text-slate-400">Fully Received</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Linked Manufacturing Orders Section -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider">Linked Manufacturing Orders Waiting For This Stock</h4>
                            <span class="px-2 py-0.5 text-[9px] font-black uppercase bg-indigo-50 text-indigo-700 rounded-lg">Real-Time Demand Scan</span>
                        </div>
                        
                        @if(!empty($linkedMos))
                            <div class="space-y-3 max-h-48 overflow-y-auto pr-1">
                                @foreach($linkedMos as $mo)
                                    <div class="bg-white border border-slate-250 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 text-xs">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-800">{{ $mo['mo_number'] }}</span>
                                                <span class="px-2 py-0.5 text-[9px] font-bold rounded bg-slate-100 text-slate-500 uppercase">{{ $mo['status'] }}</span>
                                            </div>
                                            <div class="text-[10px] text-indigo-600 font-bold mt-1">Finished Good: {{ $mo['finished_good'] }} ({{ $mo['finished_sku'] }})</div>
                                        </div>
                                        <div class="space-y-1 text-right">
                                            @foreach($mo['components'] as $comp)
                                                <div class="flex items-center gap-2 justify-end text-[11px]">
                                                    <span class="text-slate-550 font-medium">{{ $comp['name'] }} (Shortage: <b class="text-rose-600 font-bold font-mono">{{ number_format($comp['shortage'], 2) }}</b>)</span>
                                                    @if($comp['will_satisfy'])
                                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Will Satisfy Shortage</span>
                                                    @else
                                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded bg-amber-50 text-amber-700 border border-amber-200">Partial Intake</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic">No active Manufacturing Orders are currently waiting for raw materials included in this Purchase Order.</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Target Warehouse Bin (Inbound stock injection)</label>
                            <select wire:model="bin_id" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition cursor-pointer" required>
                                <option value="">Select a bin...</option>
                                @foreach($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->warehouse->name }} &rarr; {{ $bin->bin_code }} ({{ $bin->zone_code }})</option>
                                @endforeach
                            </select>
                            @error('bin_id') <span class="text-rose-600 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Reconciliation Notes (Optional)</label>
                            <textarea wire:model="notes" rows="1" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="Log any damage observations or cargo discrepancies..."></textarea>
                        </div>
                    </div>

                    <div class="mt-5 sm:mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-xl transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-600/10 hover:shadow-emerald-600/25 transition">
                            Confirm Receipt & Post Accounts Payable
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Redesigned View GRN Modal -->
    @if($showViewModal && $viewGrnDetails)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showViewModal', false)"></div>
            
            <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl relative z-50 border border-slate-100">
                <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Goods Receipt Details</h3>
                        <p class="text-xs text-slate-400 mt-0.5">GRN-#{{ str_pad($viewGrnDetails->id, 5, '0', STR_PAD_LEFT) }}</p>
                    </div>
                    <button wire:click="$set('showViewModal', false)" class="text-slate-400 hover:text-slate-550">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6 text-xs text-slate-650 font-semibold bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <p><span class="text-slate-400 font-bold mr-1">PO Reference:</span> PO-#{{ $viewGrnDetails->purchase_order_id }}</p>
                        <p class="mt-1"><span class="text-slate-400 font-bold mr-1">Supplier Partner:</span> {{ $viewGrnDetails->purchaseOrder->supplier->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p><span class="text-slate-400 font-bold mr-1">Received By:</span> {{ $viewGrnDetails->user->name ?? 'Unknown' }}</p>
                        <p class="mt-1"><span class="text-slate-400 font-bold mr-1">Receipt Date:</span> {{ $viewGrnDetails->created_at->format(setting('date_format', 'Y-m-d') . ' ' . setting('time_format', 'H:i')) }}</p>
                    </div>
                    @if($viewGrnDetails->notes)
                    <div class="col-span-2 mt-2 border-t border-slate-200/60 pt-2 text-slate-500">
                        <p class="font-normal italic"><span class="font-bold text-slate-400 not-italic mr-1">Inspector Notes:</span> "{{ $viewGrnDetails->notes }}"</p>
                    </div>
                    @endif
                </div>

                <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Received Cargo Items</h4>
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-slate-100 text-xs text-slate-650">
                        <thead class="bg-slate-50">
                            <tr class="font-bold text-slate-500">
                                <th class="px-4 py-2.5 text-left">Product Item</th>
                                <th class="px-4 py-2.5 text-left">SKU Code</th>
                                <th class="px-4 py-2.5 text-left">Warehouse Destination Bin</th>
                                <th class="px-4 py-2.5 text-right">Qty Received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse(\App\Models\InventoryTransaction::where('reference_type', \App\Models\GoodsReceiptNote::class)->where('reference_id', $viewGrnDetails->id)->with(['product', 'toBin.warehouse'])->get() as $tx)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $tx->product->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-500">{{ $tx->product->sku ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3">
                                        @if($tx->toBin)
                                            <div class="font-semibold text-slate-700">{{ $tx->toBin->bin_code }} ({{ $tx->toBin->zone_code }})</div>
                                            @if($tx->toBin->warehouse)
                                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $tx->toBin->warehouse->name }}</div>
                                            @endif
                                        @else
                                            <span class="text-slate-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-600 font-mono">+{{ $tx->quantity }} units</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-slate-400">No items found for this record.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                    <button wire:click="$set('showViewModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

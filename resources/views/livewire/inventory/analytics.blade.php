<?php

use Livewire\Volt\Component;
use App\Models\InventoryTransaction;
use App\Models\GoodsReceiptNote;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $inventoryFlow = [];

    public function mount()
    {
        if (!auth()->user()->can('view inventory')) {
            abort(403);
        }

        $this->loadData();
    }

    public function loadData()
    {
        // Calculate Flow: Supplier -> Warehouse -> Product based on historical GRN receipts
        $txs = InventoryTransaction::where('type', 'IN')
            ->where('reference_type', GoodsReceiptNote::class)
            ->with(['grn.purchaseOrder.supplier', 'toBin.warehouse', 'product'])
            ->get();

        $supplierToWarehouse = [];
        $warehouseToProduct = [];

        foreach ($txs as $tx) {
            $supplierName = $tx->grn->purchaseOrder->supplier->name ?? 'Unknown Supplier';
            $warehouseName = $tx->toBin->warehouse->name ?? 'Unknown Warehouse';
            $productName = $tx->product->name ?? 'Unknown Product';
            $qty = (float) $tx->quantity;

            // Aggregate Supplier -> Warehouse
            $s2wKey = $supplierName . '|' . $warehouseName;
            if (!isset($supplierToWarehouse[$s2wKey])) {
                $supplierToWarehouse[$s2wKey] = ['from' => 'Supplier: ' . $supplierName, 'to' => 'Warehouse: ' . $warehouseName, 'qty' => 0];
            }
            $supplierToWarehouse[$s2wKey]['qty'] += $qty;

            // Aggregate Warehouse -> Product
            $w2pKey = $warehouseName . '|' . $productName;
            if (!isset($warehouseToProduct[$w2pKey])) {
                $warehouseToProduct[$w2pKey] = ['from' => 'Warehouse: ' . $warehouseName, 'to' => 'Product: ' . $productName, 'qty' => 0];
            }
            $warehouseToProduct[$w2pKey]['qty'] += $qty;
        }

        foreach ($supplierToWarehouse as $edge) {
            $this->inventoryFlow[] = [$edge['from'], $edge['to'], $edge['qty']];
        }
        foreach ($warehouseToProduct as $edge) {
            $this->inventoryFlow[] = [$edge['from'], $edge['to'], $edge['qty']];
        }
    }
}; ?>

<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <div class="mb-8 md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-3xl font-black leading-7 text-gray-900 sm:text-4xl sm:truncate tracking-tight">
                    Inventory Analytics & Data Flow
                </h2>
                <p class="mt-2 text-sm text-gray-500 font-medium">
                    Visualizing the flow of goods from Suppliers, through Warehouses, down to the specific Products.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 mb-8">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
                <h3 class="text-lg font-black text-gray-900 mb-2">Supply Chain Tracing (DFD)</h3>
                <p class="text-sm text-gray-500 mb-6">Traces total historical quantities received from each supplier.</p>
                <p class="text-sm text-gray-500 mb-6 italic">Visualizations have been removed to focus on operational data.</p>
            </div>
        </div>

    </div>

</div>

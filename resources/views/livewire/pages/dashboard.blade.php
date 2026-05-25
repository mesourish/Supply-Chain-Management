<?php

use function Livewire\Volt\{state, mount};
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\InventoryTransaction;

state([
    'lowStockCount' => 0,
    'openPurchases' => 0,
    'openSales' => 0,
    'inTransitShipments' => 0,
    'recentTransactions' => [],
]);

mount(function () {
    // Basic logic for current_stock is omitted since we use transaction sums
    // For now, let's just count products with reorder_level > 0 as a placeholder
    // Ideally we'd join with transactions
    $this->lowStockCount = Product::count(); // Placeholder

    $this->openPurchases = PurchaseOrder::whereIn('status', ['draft', 'approved'])->count();
    $this->openSales = SalesOrder::whereIn('status', ['draft', 'confirmed'])->count();
    $this->inTransitShipments = Shipment::where('status', 'In Transit')->count();

    $this->recentTransactions = InventoryTransaction::with(['product', 'user'])
        ->orderBy('created_at', 'desc')
        ->take(10)
        ->get();
});

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('SCM Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Low Stock -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                    <div class="text-sm font-medium text-gray-500 uppercase">Low Stock Items</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ $lowStockCount }}</div>
                </div>

                <!-- Open POs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="text-sm font-medium text-gray-500 uppercase">Open Purchase Orders</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ $openPurchases }}</div>
                </div>

                <!-- Open SOs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                    <div class="text-sm font-medium text-gray-500 uppercase">Open Sales Orders</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ $openSales }}</div>
                </div>

                <!-- In Transit -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-yellow-500">
                    <div class="text-sm font-medium text-gray-500 uppercase">Active Shipments</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ $inTransitShipments }}</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4">Recent Inventory Movements</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($recentTransactions as $tx)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->created_at->diffForHumans() }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $tx->product->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $tx->type === 'IN' ? 'bg-green-100 text-green-800' : ($tx->type === 'OUT' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                                {{ $tx->type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->quantity }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->user->name ?? 'System' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent transactions</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

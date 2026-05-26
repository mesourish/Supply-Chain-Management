<?php

use App\Models\InventoryTransaction;
use App\Models\Product;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Filters
    public $filter_product_id = '';
    public $filter_type = '';

    public $products;

    public function mount()
    {
        $this->products = Product::orderBy('name')->get();
    }

    public function with()
    {
        $query = InventoryTransaction::with(['product', 'fromBin.warehouse', 'toBin.warehouse', 'user'])
            ->latest();

        if ($this->filter_product_id) {
            $query->where('product_id', $this->filter_product_id);
        }
        if ($this->filter_type) {
            $query->where('type', $this->filter_type);
        }

        return [
            'transactions' => $query->paginate(15),
        ];
    }
}; ?>

<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Header Section -->
        <div class="mb-8 md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-3xl font-extrabold leading-7 text-gray-900 sm:text-4xl sm:truncate tracking-tight">
                    Inventory Audit Ledger
                </h2>
                <p class="mt-2 text-sm text-gray-500">
                    A read-only, chronological immutable ledger tracking all physical stock movements, sourcing operations, and shipments.
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('inventory.adjustments') }}" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                    Go to Adjustments
                </a>
            </div>
        </div>

        <!-- Filters Box -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Filter Audit Records</h3>
                
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-full sm:w-64">
                        <select wire:model.live="filter_product_id" class="block w-full border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg text-sm transition-colors py-2 pl-3 pr-10">
                            <option value="">All Products</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-48">
                        <select wire:model.live="filter_type" class="block w-full border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg text-sm transition-colors py-2 pl-3 pr-10">
                            <option value="">All Movement Types</option>
                            <option value="adjustment_in">Adjustment In (+)</option>
                            <option value="adjustment_out">Adjustment Out (-)</option>
                            <option value="transfer">Bin Transfer</option>
                            <option value="purchase_order">PO Received</option>
                            <option value="sales_order">SO Shipped</option>
                            <option value="rma_return">RMA Return</option>
                            <option value="project_allocation">Project Reserved</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Table Card -->
        <div class="bg-white shadow-sm border border-gray-100 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/75">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Timestamp / Operator</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source Bin</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Destination Bin</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference & Reason</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($transactions as $txn)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="font-medium text-gray-900">{{ $txn->created_at->format('Y-m-d H:i') }}</div>
                                    <div class="text-xs text-gray-400 font-mono">{{ $txn->user->name ?? 'System Process' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="font-semibold text-gray-900">{{ $txn->product?->sku ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs">{{ $txn->product?->name ?? 'Deleted Product' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $badgeClass = match($txn->type) {
                                            'adjustment_in', 'in' => 'bg-green-50 text-green-700 border-green-100',
                                            'adjustment_out', 'out' => 'bg-rose-50 text-rose-700 border-rose-100',
                                            'transfer' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                                            'purchase_order' => 'bg-amber-50 text-amber-700 border-amber-100',
                                            'sales_order' => 'bg-teal-50 text-teal-700 border-teal-100',
                                            'rma_return' => 'bg-purple-50 text-purple-700 border-purple-100',
                                            default => 'bg-gray-50 text-gray-600 border-gray-100'
                                        };
                                        $label = match($txn->type) {
                                            'adjustment_in', 'in' => 'ADJUST IN',
                                            'adjustment_out', 'out' => 'ADJUST OUT',
                                            'transfer' => 'TRANSFER',
                                            'purchase_order' => 'PO INBOUND',
                                            'sales_order' => 'SO FULFILL',
                                            'rma_return' => 'RMA RETURN',
                                            default => strtoupper($txn->type)
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                        {{ $label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $isNegative = in_array($txn->type, ['adjustment_out', 'out', 'sales_order']);
                                        $colorClass = $isNegative ? 'text-rose-600' : ($txn->type === 'transfer' ? 'text-indigo-600' : 'text-green-600');
                                        $prefix = $isNegative ? '-' : ($txn->type === 'transfer' ? '⇅' : '+');
                                    @endphp
                                    <span class="font-bold {{ $colorClass }}">
                                        {{ $prefix }} {{ $txn->quantity }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($txn->fromBin)
                                        <div class="font-medium text-gray-700">{{ $txn->fromBin->warehouse->name }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $txn->fromBin->full_label }}</div>
                                    @else
                                        <span class="text-gray-300 font-mono">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($txn->toBin)
                                        <div class="font-medium text-gray-700">{{ $txn->toBin->warehouse->name }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $txn->toBin->full_label }}</div>
                                    @else
                                        <span class="text-gray-300 font-mono">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                                    @if($txn->reference_type)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 mb-1">
                                            {{ $txn->reference_type }} #{{ $txn->reference_id }}
                                        </span><br>
                                    @endif
                                    <div class="text-xs italic text-gray-600 truncate" title="{{ $txn->notes }}">
                                        {{ $txn->notes ?: 'No description recorded.' }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 font-semibold">No Audit Records Found</h3>
                                    <p class="mt-1 text-sm text-gray-500">No transactions recorded for the selected filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($transactions->hasPages())
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>

    </div>
</div>

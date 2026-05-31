<?php

use function Livewire\Volt\{state, mount, rules};
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\Product;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

state([
    'rma' => null,
    'items' => [],
]);

mount(function ($id) {
    if (!auth()->user()->can('view returns')) abort(403);
    $this->rma = ReturnRequest::with(['customer', 'salesOrder'])->findOrFail($id);
    $this->items = ReturnRequestItem::with('product')->where('return_request_id', $id)->get()->toArray();
});

$saveItems = function () {
    if (!auth()->user()->can('edit returns')) abort(403);

    DB::transaction(function () {
        foreach ($this->items as $itemData) {
            $item = ReturnRequestItem::find($itemData['id']);
            $item->condition = $itemData['condition'];
            $item->resolution = $itemData['resolution'];
            $item->save();
        }
    });

    $this->rma->refresh();
    $this->items = ReturnRequestItem::with('product')->where('return_request_id', $this->rma->id)->get()->toArray();
};

$resolveRMA = function () {
    if (!auth()->user()->can('edit returns')) abort(403);
    
    // Check if any items are still pending
    $hasPending = collect($this->items)->contains(function ($item) {
        return $item['condition'] === 'pending' || $item['resolution'] === 'pending';
    });

    if ($hasPending) {
        $this->addError('general', 'All items must have a condition and resolution selected before resolving.');
        return;
    }

    DB::transaction(function () {
        foreach ($this->items as $itemData) {
            $item = ReturnRequestItem::with('product')->find($itemData['id']);
            $item->condition = $itemData['condition'];
            $item->resolution = $itemData['resolution'];
            $item->save();

            // 1. Process Inventory
            if ($item->condition === 'good') {
                $product = $item->product;
                
                // Get a default bin to restock to (or create logic to select bin)
                $defaultBin = \App\Models\WarehouseBin::first();
                $binId = $defaultBin ? $defaultBin->id : 1;

                $stock = \App\Models\BinProductStock::firstOrCreate(
                    ['warehouse_bin_id' => $binId, 'product_id' => $product->id],
                    ['quantity' => 0]
                );
                $stock->increment('quantity', $item->quantity);

                InventoryTransaction::create([
                    'product_id' => $product->id,
                    'to_bin_id' => $binId,
                    'type' => 'adjustment',
                    'quantity' => $item->quantity,
                    'reference_type' => 'App\Models\ReturnRequest',
                    'reference_id' => $this->rma->id,
                    'notes' => 'Returned item in good condition',
                    'user_id' => auth()->id(),
                ]);
            }
        }

        $this->rma->status = 'resolved';
        $this->rma->save();
    });

    $this->rma->refresh();
    $this->items = ReturnRequestItem::with('product')->where('return_request_id', $this->rma->id)->get()->toArray();
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Process RMA: ') }} RMA-{{ str_pad($rma->id, 5, '0', STR_PAD_LEFT) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if($errors->has('general'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ $errors->first('general') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Customer</p>
                        <p class="font-medium">
                            @if($rma->customer)
                                <a href="{{ route('customers.show', $rma->customer->id) }}" class="text-indigo-600 hover:underline">{{ $rma->customer->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Sales Order</p>
                        <p class="font-medium">SO-{{ str_pad($rma->sales_order_id, 5, '0', STR_PAD_LEFT) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="font-medium">{{ ucfirst($rma->status) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Reason</p>
                        <p class="font-medium">{{ $rma->reason }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">Returned Items</h3>
                    <form wire:submit.prevent="saveItems">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Condition</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resolution</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($items as $index => $item)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $item['product']['name'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item['quantity'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($rma->status === 'resolved')
                                                {{ ucfirst($item['condition']) }}
                                            @else
                                                <select wire:model="items.{{ $index }}.condition" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="pending">Pending</option>
                                                    <option value="good">Good (Restock)</option>
                                                    <option value="damaged">Damaged (Write-off)</option>
                                                </select>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($rma->status === 'resolved')
                                                {{ ucfirst($item['resolution']) }}
                                            @else
                                                <select wire:model="items.{{ $index }}.resolution" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="pending">Pending</option>
                                                    <option value="credit">Store Credit / Refund</option>
                                                    <option value="replacement">Replacement</option>
                                                </select>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        
                        @if($rma->status !== 'resolved')
                            <div class="mt-4 flex justify-end space-x-3">
                                <button type="submit" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 text-sm font-medium">
                                    Save Draft
                                </button>
                                <button type="button" wire:click="resolveRMA" wire:confirm="Are you sure you want to resolve this RMA? Inventory will be permanently updated." class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium">
                                    Resolve & Process
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

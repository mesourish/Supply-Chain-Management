<?php

use function Livewire\Volt\{state, mount, with, rules};
use App\Models\ReturnRequest;
use App\Models\SalesOrder;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Facades\DB;

state([
    'salesOrders' => [],
    'sales_order_id' => '',
    'reason' => '',
    'rmaId' => null,
    'isEditing' => false,
]);

rules([
    'sales_order_id' => 'required|exists:sales_orders,id',
    'reason' => 'required|string',
]);

mount(function () {
    if (!auth()->user()->can('view returns')) abort(403);
    // Only fetch orders that have been shipped or delivered (eligible for return)
    $this->salesOrders = SalesOrder::whereIn('status', ['shipped', 'delivered'])->latest()->get();
});

with(fn () => [
    'returns' => ReturnRequest::with(['salesOrder', 'customer'])->latest()->paginate(10),
]);

$save = function () {
    if (!auth()->user()->can('create returns')) abort(403);
    $this->validate();

    if ($this->rmaId) {
        $rma = ReturnRequest::findOrFail($this->rmaId);
        $rma->update([
            'sales_order_id' => $this->sales_order_id,
            'reason' => $this->reason,
        ]);
        session()->flash('message', 'RMA Updated Successfully.');
    } else {
        $order = SalesOrder::with('items')->findOrFail($this->sales_order_id);
        $reasonText = $this->reason;

        DB::transaction(function () use ($order, $reasonText) {
            $rma = ReturnRequest::create([
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => 'pending',
                'reason' => $reasonText,
            ]);

            foreach ($order->items as $item) {
                ReturnRequestItem::create([
                    'return_request_id' => $rma->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'condition' => 'pending',
                    'resolution' => 'pending',
                ]);
            }
        });
        session()->flash('message', 'RMA Created Successfully.');
    }

    $this->resetInputFields();
};

$edit = function ($id) {
    if (!auth()->user()->can('create returns')) abort(403);
    $rma = ReturnRequest::findOrFail($id);
    $this->rmaId = $rma->id;
    $this->sales_order_id = $rma->sales_order_id;
    $this->reason = $rma->reason;
    $this->isEditing = true;
};

$delete = function ($id) {
    if (!auth()->user()->can('create returns')) abort(403);
    $rma = ReturnRequest::findOrFail($id);
    $rma->items()->delete();
    $rma->delete();
    session()->flash('message', 'RMA Deleted Successfully.');
};

$resetInputFields = function () {
    $this->sales_order_id = '';
    $this->reason = '';
    $this->rmaId = null;
    $this->isEditing = false;
};
?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    
    @can('create returns')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Return Request (RMA)' : 'Create Return Request (RMA)' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="sales_order_id" value="Sales Order *" />
                        <select wire:model="sales_order_id" id="sales_order_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select a shipped/delivered order</option>
                            @foreach($salesOrders as $so)
                                <option value="{{ $so->id }}">SO-{{ str_pad($so->id, 5, '0', STR_PAD_LEFT) }} ({{ $so->customer->name ?? 'Unknown' }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('sales_order_id')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="reason" value="Reason for Return *" />
                        <textarea wire:model="reason" id="reason" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required placeholder="Describe why the customer is returning the items..."></textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update RMA' : 'Create RMA' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button type="button" wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>
    @endcan

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Returns (RMA) Directory</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RMA #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($returns as $rma)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">RMA-{{ str_pad($rma->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">SO-{{ str_pad($rma->sales_order_id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($rma->customer)
                                        {{ $rma->customer->name }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $rma->status === 'resolved' ? 'bg-green-100 text-green-800' : 
                                          ($rma->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($rma->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $rma->reason }}">
                                    {{ $rma->reason }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ url('/sales/returns/'.$rma->id) }}" class="text-green-600 hover:text-green-900 mr-3" wire:navigate>Process</a>
                                    @can('create returns')
                                    <button wire:click="edit({{ $rma->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $rma->id }})" wire:confirm="Are you sure you want to delete this RMA?" class="text-red-600 hover:text-red-900">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No return requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $returns->links() }}
            </div>

        </div>
    </div>
</div>

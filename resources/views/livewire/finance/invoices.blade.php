<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Invoice;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function with()
    {
        return [
            'invoices' => Invoice::with(['salesOrder.customer'])
                ->where('id', 'like', '%' . $this->search . '%')
                ->orWhereHas('salesOrder.customer', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })
                ->latest()
                ->paginate(15)
        ];
    }
};
?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
            🧾 Invoices
        </h1>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">All Invoices</h2>
                <div class="w-1/3">
                    <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search by Invoice ID or Customer Name..." class="w-full" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($invoices as $invoice)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">INV-#{{ $invoice->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($invoice->salesOrder && $invoice->salesOrder->customer)
                                        <a href="{{ route('customers.show', $invoice->salesOrder->customer->id) }}" class="text-indigo-600 hover:underline">{{ $invoice->salesOrder->customer->name }}</a>
                                    @else
                                        Unknown Customer
                                    @endif
                                    <div class="text-xs text-gray-400">SO-#{{ $invoice->sales_order_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${{ number_format($invoice->amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($invoice->status === 'issued')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Issued</span>
                                    @elseif($invoice->status === 'paid')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                                    @elseif($invoice->status === 'voided')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Voided</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($invoice->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $invoice->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>
</div>

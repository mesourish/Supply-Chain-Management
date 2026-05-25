
<?php

use function Livewire\Volt\{state, mount};
use App\Models\Invoice;

state([
    'invoices' => [],
    'showInvoiceModal' => false,
    'selectedInvoice' => null,
]);

mount(function () {
    if (!auth()->user()->can('view receivables')) abort(403);
    $this->invoices = Invoice::with(['salesOrder.customer', 'salesOrder.items.product'])
        ->latest()
        ->get();
});

$viewInvoice = function ($id) {
    $this->selectedInvoice = Invoice::with(['salesOrder.customer', 'salesOrder.items.product'])->findOrFail($id);
    $this->showInvoiceModal = true;
};

$changeStatus = function ($id) {
    $invoice = Invoice::findOrFail($id);
    $invoice->status = $invoice->status === 'paid' ? 'unpaid' : 'paid';
    $invoice->save();
    session()->flash('success', 'Invoice status updated successfully.');
};

$deleteInvoice = function ($id) {
    $invoice = Invoice::findOrFail($id);
    $invoice->delete();
    session()->flash('success', 'Invoice deleted successfully.');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Invoices') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">All Invoices</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SO Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Issued</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">INV-#{{ $invoice->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($invoice->salesOrder && $invoice->salesOrder->customer)
                                                <a href="{{ route('customers.show', $invoice->salesOrder->customer->id) }}" class="text-indigo-600 hover:underline">{{ $invoice->salesOrder->customer->name }}</a>
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($invoice->amount, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $invoice->status === 'paid' ? 'green' : 'yellow' }}-100 text-{{ $invoice->status === 'paid' ? 'green' : 'yellow' }}-800">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $invoice->created_at->format('M d, Y H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button wire:click="viewInvoice({{ $invoice->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">View</button>
                                            <button wire:click="changeStatus({{ $invoice->id }})" class="text-yellow-600 hover:text-yellow-900 mr-3">Toggle Status</button>
                                            <button wire:click="deleteInvoice({{ $invoice->id }})" onclick="confirm('Are you sure you want to delete this invoice?') || event.stopImmediatePropagation()" class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No invoices generated yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    @if($showInvoiceModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="$set('showInvoiceModal', false)"></div>
            <div class="inline-block w-full max-w-3xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg relative z-50">
                @if($selectedInvoice)
                <div class="flex justify-between items-center border-b pb-4 mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Invoice INV-#{{ $selectedInvoice->id }}</h3>
                    <button wire:click="$set('showInvoiceModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-6 mb-6 text-sm">
                    <div>
                        <h4 class="font-semibold text-gray-500 uppercase text-xs tracking-wider mb-2">Billed To</h4>
                        <p class="font-bold text-gray-800">{{ $selectedInvoice->salesOrder->customer->name ?? 'N/A' }}</p>
                        <p class="text-gray-600">{{ $selectedInvoice->salesOrder->customer->email ?? '' }}</p>
                        <p class="text-gray-600">{{ $selectedInvoice->salesOrder->customer->phone ?? '' }}</p>
                    </div>
                    <div class="text-right">
                        <h4 class="font-semibold text-gray-500 uppercase text-xs tracking-wider mb-2">Invoice Details</h4>
                        <p><span class="text-gray-500 mr-2">Status:</span> <span class="font-bold text-indigo-600 uppercase">{{ $selectedInvoice->status }}</span></p>
                        <p><span class="text-gray-500 mr-2">Date:</span> {{ $selectedInvoice->created_at->format('M d, Y') }}</p>
                        <p><span class="text-gray-500 mr-2">Reference SO:</span> SO-#{{ $selectedInvoice->sales_order_id }}</p>
                    </div>
                </div>

                <div class="mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($selectedInvoice->salesOrder->items as $item)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-800">{{ $item->product->name }} <br><span class="text-xs text-gray-500">{{ $item->product->sku }}</span></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right">{{ $item->quantity }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right">{{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 text-right font-medium">{{ setting('currency_symbol', '$') }}{{ number_format($item->total_price, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end border-t pt-4">
                    <div class="text-right w-64">
                        <p class="text-xl font-bold text-gray-900 border-t-2 pt-2 mt-2">
                            <span class="text-gray-600 text-lg mr-4 font-normal">Total:</span> 
                            {{ setting('currency_symbol', '$') }}{{ number_format($selectedInvoice->amount, 2) }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" wire:click="$set('showInvoiceModal', false)" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded">Close</button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

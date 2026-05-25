<?php

use function Livewire\Volt\{state, mount};
use App\Models\SalesOrder;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;

state([
    'salesOrders' => [],
    'invoices' => [],
    
    'showModal' => false,
    'selectedSo' => null,
    'bin_id' => '',
    'bins' => [],
    'showInvoiceModal' => false,
    'selectedInvoice' => null,
]);

mount(function () {
    if (!auth()->user()->can('fulfill sales_orders')) {
        abort(403);
    }
    $this->loadData();
});

$loadData = function () {
    // SOs that are confirmed but not yet shipped
    $this->salesOrders = SalesOrder::with(['customer', 'items.product'])
        ->whereIn('status', ['confirmed']) // Only confirmed orders can be fulfilled
        ->latest()
        ->get();
        
    $this->invoices = Invoice::with(['salesOrder.customer'])
        ->latest()
        ->get();
        
    $this->bins = WarehouseBin::with('warehouse')->get();
};

$fulfill = function (SalesOrder $so) {
    if (!auth()->user()->can('fulfill sales_orders')) abort(403);
    $this->selectedSo = $so;
    $this->bin_id = $this->bins->first()->id ?? null;
    $this->showModal = true;
};

$viewInvoice = function (Invoice $invoice) {
    $this->selectedInvoice = Invoice::with(['salesOrder.customer', 'salesOrder.items.product'])->find($invoice->id);
    $this->showInvoiceModal = true;
};

$confirmFulfillment = function () {
    if (!auth()->user()->can('fulfill sales_orders')) abort(403);
    
    $this->validate([
        'bin_id' => 'required|exists:warehouse_bins,id',
    ], [
        'bin_id.required' => 'You must select a warehouse bin to pick items from.'
    ]);

    DB::transaction(function () {
        // 1. Update SO status
        $so = SalesOrder::find($this->selectedSo->id);
        $so->update(['status' => 'shipped']);

        // 2. Create Inventory Transactions (OUT)
        foreach ($so->items as $item) {
            InventoryTransaction::create([
                'product_id' => $item->product_id,
                'from_bin_id' => $this->bin_id,
                'to_bin_id' => null,
                'type' => 'OUT',
                'quantity' => $item->quantity,
                'reference_type' => SalesOrder::class,
                'reference_id' => $so->id,
                'notes' => 'Shipped for SO #' . $so->id,
                'user_id' => auth()->id(),
            ]);
        }

        // 3. Create Invoice
        $invoice = Invoice::create([
            'sales_order_id' => $so->id,
            'status' => 'issued',
            'amount' => $so->total_amount,
        ]);

        // 4. Create Account Receivable
        AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $so->customer_id,
            'amount' => $so->total_amount,
            'status' => 'pending',
        ]);
    });

    $this->showModal = false;
    $this->selectedSo = null;
    $this->loadData();
    session()->flash('success', 'Order fulfilled, shipped, and invoiced successfully!');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Order Fulfillment (Pick, Pack, Ship)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            {{-- Flash messages --}}
            @if (session()->has('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-green-600 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            
            <!-- Pending Fulfillment -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    <h3 class="text-lg font-bold mb-4">Orders Ready for Fulfillment</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SO ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($salesOrders as $so)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">SO-#{{ $so->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($so->customer)
                                                <a href="{{ route('customers.show', $so->customer->id) }}" class="text-indigo-600 hover:underline">{{ $so->customer->name }}</a>
                                            @else
                                                Unknown
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ setting('currency_symbol', '$') }}{{ number_format($so->total_amount, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $so->items->sum('quantity') }} units</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @can('fulfill sales_orders')
                                            <button wire:click="fulfill({{ $so->id }})" class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700">Fulfill & Ship</button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No confirmed orders ready for fulfillment.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Invoices (Fulfilled Orders) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4">Generated Invoices</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SO Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $invoice->created_at->format('M d, Y H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button wire:click="viewInvoice({{ $invoice->id }})" class="text-indigo-600 hover:text-indigo-900">View</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No invoices generated yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Fulfill Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="$set('showModal', false)"></div>

            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg relative z-50">
                @if($selectedSo)
                <h3 class="text-lg font-bold leading-6 text-gray-900 mb-4 border-b pb-2">
                    Fulfill SO-#{{ $selectedSo->id }} ({{ $selectedSo->customer->name }})
                </h3>
                
                <div class="mb-4">
                    <h4 class="font-medium text-sm text-gray-700 mb-2">Pick List:</h4>
                    <ul class="list-disc pl-5 text-sm text-gray-600 mb-4">
                        @foreach($selectedSo->items as $item)
                            <li>{{ $item->quantity }}x {{ $item->product->name }} (SKU: {{ $item->product->sku }})</li>
                        @endforeach
                    </ul>
                </div>

                <form wire:submit="confirmFulfillment">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Pick from Warehouse Bin</label>
                        <select wire:model="bin_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select a bin...</option>
                            @foreach($bins as $bin)
                                <option value="{{ $bin->id }}">{{ $bin->warehouse->name }} - {{ $bin->full_label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">This will deduct the stock from the selected bin.</p>
                        @error('bin_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-5 sm:mt-6 flex justify-end gap-3 border-t pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Pack, Ship & Invoice
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endif

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

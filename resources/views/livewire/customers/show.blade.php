<?php

use App\Models\Customer;
use Livewire\Volt\Component;

new class extends Component {
    public Customer $customer;

    public function mount(Customer $customer)
    {
        $this->customer = $customer->load([
            'salesOrders.items',
            'invoices',
            'accountReceivables',
            'returnRequests'
        ]);
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Customer Profile') }}: {{ $customer->name }}
            </h2>
            <a href="{{ route('customers.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Customers</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Profile Info Card -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-lg">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Profile Information
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Contact Person</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->contact_person ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Email Address</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->email }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Phone Number</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Tax ID</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->tax_id ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Billing Address</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->billing_address ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Shipping Address</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->shipping_address ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Activity Tabs (Using alpine.js for simple tabs) -->
            <div x-data="{ activeTab: 'sales_orders' }" class="bg-white shadow sm:rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button @click="activeTab = 'sales_orders'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'sales_orders', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'sales_orders'}" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors">
                            Sales Orders
                        </button>
                        <button @click="activeTab = 'invoices'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'invoices', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'invoices'}" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors">
                            Invoices
                        </button>
                        <button @click="activeTab = 'receivables'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'receivables', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'receivables'}" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors">
                            Receivables
                        </button>
                        <button @click="activeTab = 'returns'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'returns', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'returns'}" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors">
                            Returns (RMA)
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Sales Orders Tab -->
                    <div x-show="activeTab === 'sales_orders'" x-cloak>
                        @if($customer->salesOrders->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($customer->salesOrders as $order)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">SO-{{ $order->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $order->created_at->format('M d, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 uppercase">{{ $order->status }}</span></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <p class="text-sm text-gray-500 py-4">No sales orders found for this customer.</p>
                        @endif
                    </div>

                    <!-- Invoices Tab -->
                    <div x-show="activeTab === 'invoices'" x-cloak style="display: none;">
                        @if($customer->invoices->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Issued</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($customer->invoices as $invoice)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">INV-{{ $invoice->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">SO-{{ $invoice->sales_order_id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $invoice->created_at->format('M d, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ setting('currency_symbol', '$') }}{{ number_format($invoice->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <p class="text-sm text-gray-500 py-4">No invoices found for this customer.</p>
                        @endif
                    </div>

                    <!-- Receivables Tab -->
                    <div x-show="activeTab === 'receivables'" x-cloak style="display: none;">
                        @if($customer->accountReceivables->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">AR #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($customer->accountReceivables as $ar)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">AR-{{ $ar->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $ar->invoice_id ? 'INV-'.$ar->invoice_id : 'Manual Entry' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $ar->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} uppercase">
                                                {{ $ar->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ setting('currency_symbol', '$') }}{{ number_format($ar->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <p class="text-sm text-gray-500 py-4">No receivables found for this customer.</p>
                        @endif
                    </div>

                    <!-- Returns Tab -->
                    <div x-show="activeTab === 'returns'" x-cloak style="display: none;">
                        @if($customer->returnRequests->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RMA #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($customer->returnRequests as $rma)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">RMA-{{ $rma->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">SO-{{ $rma->sales_order_id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $rma->created_at->format('M d, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 uppercase">{{ $rma->status }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <p class="text-sm text-gray-500 py-4">No returns found for this customer.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

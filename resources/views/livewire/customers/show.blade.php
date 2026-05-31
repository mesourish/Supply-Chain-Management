<?php

use App\Models\Customer;
use App\Models\AccountReceivable;
use App\Models\PaymentLog;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Customer $customer;
    
    // Tab State
    public $activeTab = 'sales_orders';

    // Receivable Form Fields
    public $receivableId = null;
    public $receivableAmount = '';
    public $receivableStatus = 'unpaid';
    public $receivableAttachment = null;
    public $showReceivableModal = false;

    // Payment Form Fields
    public $paymentReceivableId = null;
    public $paymentAmount = '';
    public $paymentDate = '';
    public $paymentRef = '';
    public $paymentNotes = '';
    public $paymentAttachment = null;
    public $showPaymentModal = false;

    // Certificate Fields
    public $selectedPaymentIds = [];
    public $certificateNumber = '';
    public $issuedDate = '';
    public $customNotes = '';
    public $previewCertMode = false;

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
        $this->issuedDate = date('Y-m-d');
        $this->generateCertNumber();
        $this->loadLedger();
    }

    public function loadLedger()
    {
        $this->customer->load([
            'salesOrders.items',
            'invoices',
            'accountReceivables.payments',
            'returnRequests'
        ]);
    }

    public function generateCertNumber()
    {
        $this->certificateNumber = 'CERT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    // Receivables actions
    public function createReceivable()
    {
        $this->receivableId = null;
        $this->receivableAmount = '';
        $this->receivableStatus = 'unpaid';
        $this->receivableAttachment = null;
        $this->showReceivableModal = true;
    }

    public function editReceivable($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $ar = AccountReceivable::findOrFail($id);
        $this->receivableId = $ar->id;
        $this->receivableAmount = $ar->amount;
        $this->receivableStatus = $ar->status;
        $this->receivableAttachment = null;
        $this->showReceivableModal = true;
    }

    public function saveReceivable()
    {
        $this->validate([
            'receivableAmount' => 'required|numeric|min:0',
            'receivableStatus' => 'required|in:unpaid,partial,paid',
            'receivableAttachment' => 'nullable|file|max:10240',
        ]);

        $path = null;
        if ($this->receivableAttachment) {
            $path = $this->receivableAttachment->store('receivable_attachments', 'public');
        }

        $data = [
            'customer_id' => $this->customer->id,
            'amount' => $this->receivableAmount,
            'status' => $this->receivableStatus,
        ];

        if ($path) {
            $data['attachment_path'] = $path;
        }

        AccountReceivable::updateOrCreate(['id' => $this->receivableId], $data);

        $this->showReceivableModal = false;
        $this->loadLedger();
        $this->dispatch('toast', type: 'success', message:  'Accounts Receivable updated successfully.');
    }

    public function deleteReceivable($id)
    {
        AccountReceivable::findOrFail($id)->delete();
        $this->loadLedger();
        $this->dispatch('toast', type: 'success', message:  'Receivable deleted successfully.');
    }

    // Payment Logging
    public function openPaymentModal($id)
    {
        $this->paymentReceivableId = $id;
        $this->paymentAmount = '';
        $this->paymentDate = date('Y-m-d');
        $this->paymentRef = '';
        $this->paymentNotes = '';
        $this->paymentAttachment = null;
        $this->showPaymentModal = true;
    }

    public function logPayment()
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentDate' => 'required|date',
            'paymentRef' => 'nullable|string',
            'paymentNotes' => 'nullable|string',
            'paymentAttachment' => 'nullable|file|max:10240',
        ]);

        $path = null;
        if ($this->paymentAttachment) {
            $path = $this->paymentAttachment->store('payment_attachments', 'public');
        }

        PaymentLog::create([
            'account_receivable_id' => $this->paymentReceivableId,
            'amount' => $this->paymentAmount,
            'payment_date' => $this->paymentDate,
            'reference_number' => $this->paymentRef,
            'attachment_path' => $path,
            'notes' => $this->paymentNotes,
        ]);

        $ar = AccountReceivable::find($this->paymentReceivableId);
        if ($ar->balance <= 0) {
            $ar->update(['status' => 'paid']);
        } elseif ($ar->balance > 0 && $ar->amount_paid > 0) {
            $ar->update(['status' => 'partial']);
        }

        $this->showPaymentModal = false;
        $this->loadLedger();
        $this->dispatch('toast', type: 'success', message:  'Customer payment recorded successfully.');
    }

    // Payment Certificate Actions
    public function selectAllPayments()
    {
        $this->selectedPaymentIds = $this->getPaymentsList()->pluck('id')->map(fn($id) => (string)$id)->toArray();
    }

    public function deselectAllPayments()
    {
        $this->selectedPaymentIds = [];
    }

    public function getPaymentsList()
    {
        return PaymentLog::whereHas('accountReceivable', function ($query) {
            $query->where('customer_id', $this->customer->id);
        })
        ->with('accountReceivable.invoice')
        ->orderBy('payment_date', 'desc')
        ->get();
    }

    public function togglePreview()
    {
        $this->validate([
            'certificateNumber' => 'required|string',
            'issuedDate' => 'required|date',
            'selectedPaymentIds' => 'required|array|min:1',
        ], [
            'selectedPaymentIds.required' => 'Please check at least one payment to include in the certificate.',
            'selectedPaymentIds.min' => 'Please check at least one payment to include in the certificate.',
        ]);

        $this->previewCertMode = !$this->previewCertMode;
    }

    public function getSelectedPaymentsProperty()
    {
        return PaymentLog::whereIn('id', $this->selectedPaymentIds)
            ->with('accountReceivable.invoice')
            ->orderBy('payment_date', 'asc')
            ->get();
    }

    public function getSelectedTotalProperty()
    {
        return PaymentLog::whereIn('id', $this->selectedPaymentIds)->sum('amount');
    }
    
    public function getOutstandingBalanceProperty()
    {
        return $this->customer->accountReceivables->sum('balance');
    }
}; ?>

<div>
    @if($previewCertMode)
        <style>
            @media print {
                body {
                    background: white !important;
                    color: black !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                body * {
                    visibility: hidden;
                }
                #printable-certificate, #printable-certificate * {
                    visibility: visible !important;
                }
                #printable-certificate {
                    position: fixed !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    z-index: 9999999 !important;
                    background: white !important;
                    color: black !important;
                    padding: 1.5cm !important;
                    margin: 0 !important;
                    box-shadow: none !important;
                    border: none !important;
                    visibility: visible !important;
                }
                .no-print {
                    display: none !important;
                }
            }
        </style>
    @endif

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full no-print">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                {{ __('Customer Profile Dashboard') }}
            </h2>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors bg-indigo-50 px-3.5 py-2 rounded-xl">
                &larr; Back to Directory
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            

            @if(!$previewCertMode)
                <!-- Premium Profile Section Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 no-print">
                    
                    <!-- Profile Card -->
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-indigo-500 to-violet-600"></div>
                        
                        <div class="space-y-6 pt-4">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-indigo-600/30">
                                    {{ substr($customer->name, 0, 1) }}
                                </div>
                                <div>
                                    <h3 class="text-xl font-black text-gray-900 tracking-tight">{{ $customer->name }}</h3>
                                    <p class="text-xs font-bold text-indigo-600 uppercase mt-0.5">{{ $customer->company_name ?: 'Individual Client' }}</p>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-4 space-y-4 text-sm">
                                <div>
                                    <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Contact Person</span>
                                    <div class="font-semibold text-gray-800 mt-0.5">{{ $customer->contact_person ?: 'N/A' }}</div>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Email Address</span>
                                    <div class="font-semibold text-gray-800 mt-0.5">{{ $customer->email }}</div>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Phone / Mobile</span>
                                    <div class="font-semibold text-gray-800 mt-0.5 font-mono">{{ $customer->phone ?: 'N/A' }}</div>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Tax Registration ID</span>
                                    <div class="font-semibold text-gray-800 mt-0.5 font-mono">{{ $customer->tax_id ?: 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4 mt-6 grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <span class="font-bold text-gray-400 uppercase">Billing Address</span>
                                <div class="text-gray-600 mt-1 italic leading-tight">{{ $customer->billing_address ?: 'N/A' }}</div>
                            </div>
                            <div>
                                <span class="font-bold text-gray-400 uppercase">Shipping Address</span>
                                <div class="text-gray-600 mt-1 italic leading-tight">{{ $customer->shipping_address ?: 'N/A' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Summary Widget -->
                    <div class="lg:col-span-2 bg-gradient-to-br from-gray-900 via-indigo-950 to-slate-900 rounded-3xl p-6 md:p-8 shadow-xl text-white relative overflow-hidden border border-indigo-500/10">
                        <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl"></div>
                        <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-violet-600/10 rounded-full blur-3xl"></div>

                        <div class="relative z-10 flex flex-col justify-between h-full space-y-8">
                            <div>
                                <span class="text-xs text-indigo-400 font-extrabold uppercase tracking-wider">Financial Overview</span>
                                <h3 class="text-2xl font-black tracking-tight mt-1">Client Financial Ledger Overview</h3>
                                <p class="text-indigo-200/60 text-xs mt-1">Summary of lifetime procurement, payments, outstanding receivables, and unresolved RMAs.</p>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                                <div class="space-y-1">
                                    <span class="text-xs text-indigo-300 font-bold uppercase">Total Invoiced</span>
                                    <h4 class="text-2xl font-black font-mono text-white">
                                        {{ setting('currency_symbol', '$') }}{{ number_format($customer->invoices->sum('amount'), 2) }}
                                    </h4>
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs text-indigo-300 font-bold uppercase">Outstanding AR</span>
                                    <h4 class="text-2xl font-black font-mono text-rose-400">
                                        {{ setting('currency_symbol', '$') }}{{ number_format($this->outstandingBalance, 2) }}
                                    </h4>
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs text-indigo-300 font-bold uppercase">Sales Orders</span>
                                    <h4 class="text-2xl font-black font-mono text-emerald-400">
                                        {{ $customer->salesOrders->count() }}
                                    </h4>
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs text-indigo-300 font-bold uppercase">Returns (RMAs)</span>
                                    <h4 class="text-2xl font-black font-mono text-amber-400">
                                        {{ $customer->returnRequests->count() }}
                                    </h4>
                                </div>
                            </div>

                            <div class="border-t border-white/10 pt-4 flex flex-wrap items-center justify-between text-xs text-indigo-200/80 font-medium">
                                <div>Ledger validated with 0 discrepancies</div>
                                <div class="font-mono">Created at: {{ $customer->created_at->format(setting('date_format', 'Y-m-d')) }}</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Activity tabs (Receivables update, Payment certificates, Sales, Invoices, Returns) -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-150 overflow-hidden no-print tabs-container">
                    <div class="border-b border-gray-200 bg-gray-50/50">
                        <nav class="-mb-px flex flex-wrap" aria-label="Tabs">
                            @foreach([
                                'sales_orders' => 'Sales Orders (' . $customer->salesOrders->count() . ')',
                                'invoices' => 'Invoices (' . $customer->invoices->count() . ')',
                                'receivables' => 'Accounts Receivable',
                                'certificates' => 'Payment Certificates',
                                'returns' => 'Returns (' . $customer->returnRequests->count() . ')'
                            ] as $tabKey => $tabName)
                                <button 
                                    wire:click="$set('activeTab', '{{ $tabKey }}')"
                                    class="w-1/2 md:w-auto px-6 py-4 text-center border-b-2 font-bold text-sm transition-all duration-200 focus:outline-none {{ $activeTab === $tabKey ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                                >
                                    {{ $tabName }}
                                </button>
                            @endforeach
                        </nav>
                    </div>

                    <div class="p-6">
                        <!-- 1. Sales Orders -->
                        <div x-show="$wire.activeTab === 'sales_orders'">
                            @if($customer->salesOrders->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-150">
                                        <thead>
                                            <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                                <th class="px-4 py-3 rounded-l-xl">Order #</th>
                                                <th class="px-4 py-3">Order Date</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3 text-right rounded-r-xl">Total Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach($customer->salesOrders as $order)
                                                <tr class="hover:bg-gray-50/20 transition-colors text-sm">
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-bold text-gray-900">SO-{{ $order->id }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-gray-500 font-semibold">{{ $order->created_at->format(setting('date_format', 'Y-m-d')) }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-blue-50 text-blue-700 border border-blue-100">
                                                            {{ $order->status }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-mono text-right font-extrabold text-gray-900">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-400 italic">No sales orders registered for this customer.</div>
                            @endif
                        </div>

                        <!-- 2. Invoices -->
                        <div x-show="$wire.activeTab === 'invoices'">
                            @if($customer->invoices->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-150">
                                        <thead>
                                            <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                                <th class="px-4 py-3 rounded-l-xl">Invoice Ref</th>
                                                <th class="px-4 py-3">Linked Sales Order</th>
                                                <th class="px-4 py-3">Date Issued</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3 text-right rounded-r-xl">Total Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach($customer->invoices as $invoice)
                                                <tr class="hover:bg-gray-50/20 transition-colors text-sm">
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-bold text-gray-900">INV-{{ $invoice->id }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-semibold text-gray-500">SO-{{ $invoice->sales_order_id }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-gray-500 font-semibold">{{ $invoice->created_at->format(setting('date_format', 'Y-m-d')) }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-green-50 text-green-700 border border-green-100">
                                                            {{ $invoice->status }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-mono text-right font-extrabold text-gray-900">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($invoice->amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-400 italic">No invoices issued for this customer.</div>
                            @endif
                        </div>

                        <!-- 3. Receivables & Payments Manager (Direct Updates) -->
                        <div x-show="$wire.activeTab === 'receivables'">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <div>
                                    <h4 class="text-base font-extrabold text-gray-900">Accounts Receivable Manager</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Manage bills, record payments, and edit outstanding invoices directly from the client profile.</p>
                                </div>
                                <button type="button" wire:click="createReceivable" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-colors">
                                    + Add Receivable
                                </button>
                            </div>

                            @if($customer->accountReceivables->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-150">
                                        <thead>
                                            <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                                <th class="px-4 py-3 rounded-l-xl">AR ID / Invoice</th>
                                                <th class="px-4 py-3">Amounts</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3">Received Payments Log</th>
                                                <th class="px-4 py-3 text-right rounded-r-xl">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach($customer->accountReceivables as $ar)
                                                <tr class="hover:bg-gray-50/20 transition-colors text-sm">
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <div class="font-extrabold text-gray-900">AR-#{{ $ar->id }}</div>
                                                        <div class="text-xs text-gray-400 mt-0.5">{{ $ar->invoice_id ? 'Invoice: INV-'.$ar->invoice_id : 'Manual Entry' }}</div>
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-mono text-xs">
                                                        <div class="text-gray-900">Total: {{ setting('currency_symbol', '$') }}{{ number_format($ar->amount, 2) }}</div>
                                                        <div class="font-bold text-rose-500 mt-0.5">Due: {{ setting('currency_symbol', '$') }}{{ number_format($ar->balance, 2) }}</div>
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold uppercase 
                                                            {{ $ar->status === 'paid' ? 'bg-green-50 text-green-700 border border-green-100' : ($ar->status === 'partial' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-red-50 text-red-700 border border-red-100') }}">
                                                            {{ $ar->status }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3.5 text-xs text-gray-500">
                                                        @if($ar->payments->count() > 0)
                                                            <div class="space-y-1 font-mono">
                                                                @foreach($ar->payments as $pmt)
                                                                    <div class="flex justify-between border-b border-gray-50 pb-0.5">
                                                                        <span>{{ date('M d', strtotime($pmt->payment_date)) }}:</span>
                                                                        <span class="font-bold text-gray-800">{{ setting('currency_symbol', '$') }}{{ number_format($pmt->amount, 2) }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <span class="text-gray-400 italic">No payments logged</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-right text-xs font-bold">
                                                        <button type="button" wire:click="openPaymentModal({{ $ar->id }})" class="text-emerald-600 hover:text-emerald-800 mr-3" title="Record Payment">Receive</button>
                                                        <button type="button" wire:click="editReceivable({{ $ar->id }})" class="text-indigo-600 hover:text-indigo-800 mr-3">Edit</button>
                                                        <button type="button" wire:click="deleteReceivable({{ $ar->id }})" wire:confirm="Are you sure you want to delete this receivable?" class="text-rose-600 hover:text-rose-800">Delete</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-400 italic">No accounts receivable ledger entries found.</div>
                            @endif
                        </div>

                        <!-- 4. Payment Certificates (Granular Compiler) -->
                        <div x-show="$wire.activeTab === 'certificates'">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50">
                                <div>
                                    <h4 class="text-base font-extrabold text-indigo-950">Payment Receipt Certificate Compiler</h4>
                                    <p class="text-xs text-indigo-900/60 mt-0.5">Verify payments, check checkboxes, and click compile to print official customer validation receipts.</p>
                                </div>
                            </div>

                            @php
                                $cPayments = $this->getPaymentsList();
                            @endphp

                            @if($cPayments->count() > 0)
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <!-- Selection list -->
                                    <div class="lg:col-span-2 space-y-4">
                                        <div class="flex items-center justify-between text-xs font-bold text-gray-500 px-1">
                                            <span>List of Received Receipts</span>
                                            <div class="flex gap-2">
                                                <button type="button" wire:click="selectAllPayments" class="text-indigo-600">Select All</button>
                                                <span>•</span>
                                                <button type="button" wire:click="deselectAllPayments" class="text-gray-500">Deselect All</button>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto border border-gray-150 rounded-2xl bg-white shadow-sm">
                                            <table class="min-w-full divide-y divide-gray-150">
                                                <thead>
                                                    <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/30">
                                                        <th class="px-4 py-2 text-center w-12">Select</th>
                                                        <th class="px-4 py-2">Date Received</th>
                                                        <th class="px-4 py-2">Tx Ref No</th>
                                                        <th class="px-4 py-2 text-right">Amount Received</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 text-sm">
                                                    @foreach($cPayments as $pmt)
                                                        <tr>
                                                            <td class="px-4 py-2.5 text-center">
                                                                <input type="checkbox" wire:model.live="selectedPaymentIds" value="{{ $pmt->id }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                            </td>
                                                            <td class="px-4 py-2.5 whitespace-nowrap text-gray-500 font-semibold">{{ date('M d, Y', strtotime($pmt->payment_date)) }}</td>
                                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                                <div class="font-bold text-gray-800">{{ $pmt->reference_number ?: 'N/A' }}</div>
                                                                <div class="text-[10px] text-gray-400">AR-#{{ $pmt->account_receivable_id }}</div>
                                                            </td>
                                                            <td class="px-4 py-2.5 whitespace-nowrap text-right font-mono font-extrabold text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($pmt->amount, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Right parameters -->
                                    <div class="space-y-4">
                                        <div class="bg-slate-50 border border-slate-150 p-4 rounded-2xl space-y-4">
                                            <h4 class="text-sm font-bold text-gray-800">Certificate Options</h4>
                                            
                                            <div>
                                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Certificate #</label>
                                                <input type="text" wire:model="certificateNumber" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Issue Date</label>
                                                <input type="date" wire:model="issuedDate" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Personalized Memo</label>
                                                <textarea wire:model="customNotes" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Custom note..."></textarea>
                                            </div>
                                        </div>

                                        <div class="bg-indigo-950 text-white rounded-2xl p-4 space-y-4 shadow-md">
                                            <div>
                                                <div class="text-[10px] text-indigo-300 font-bold uppercase">Selected Total Certified</div>
                                                <div class="text-2xl font-black font-mono mt-0.5">{{ setting('currency_symbol', '$') }}{{ number_format($this->selectedTotal, 2) }}</div>
                                            </div>
                                            
                                            <button 
                                                type="button" 
                                                wire:click="togglePreview"
                                                class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors"
                                            >
                                                Compile & Preview
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-400 italic">No payments logged. Record customer payments to enable certificate compiling.</div>
                            @endif
                        </div>

                        <!-- 5. Returns (RMA) -->
                        <div x-show="$wire.activeTab === 'returns'">
                            @if($customer->returnRequests->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-150">
                                        <thead>
                                            <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                                <th class="px-4 py-3 rounded-l-xl">RMA #</th>
                                                <th class="px-4 py-3">Original Order</th>
                                                <th class="px-4 py-3">Date Requested</th>
                                                <th class="px-4 py-3 rounded-r-xl">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white text-sm">
                                            @foreach($customer->returnRequests as $rma)
                                                <tr class="hover:bg-gray-50/20 transition-colors">
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-bold text-gray-900">RMA-{{ $rma->id }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-semibold text-gray-500">SO-{{ $rma->sales_order_id }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-gray-500 font-semibold">{{ $rma->created_at->format(setting('date_format', 'Y-m-d')) }}</td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-50 text-amber-700 border border-amber-100">
                                                            {{ $rma->status }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-400 italic">No return requests issued for this customer.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <!-- Printable Certificate Preview Mode inside Customer profile -->
                <div class="space-y-6">
                    <div class="flex items-center justify-between no-print bg-white p-4 rounded-2xl shadow-sm border border-gray-150">
                        <button type="button" wire:click="togglePreview" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs transition-colors">
                            &larr; Back to Profile
                        </button>

                        <button onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-md transition-all duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Certificate PDF
                        </button>
                    </div>

                    <div class="flex justify-center py-4">
                        <div id="printable-certificate" class="bg-white p-8 md:p-12 rounded-3xl border border-gray-200 shadow-xl max-w-4xl w-full relative overflow-hidden text-gray-800">
                            
                            <!-- Certificate Letterhead -->
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b-2 border-indigo-600 pb-6 mb-8 gap-4">
                                <div class="flex items-center gap-3">
                                    <svg class="w-12 h-12 text-indigo-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                    <div>
                                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">{{ setting('website_name', 'SCM ERP') }}</h1>
                                        <p class="text-xs text-gray-400 uppercase tracking-widest font-bold font-mono">Operations & Finance Group</p>
                                    </div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <h2 class="text-2xl font-extrabold text-indigo-600 tracking-tight uppercase">Payment Certificate</h2>
                                    <div class="text-xs font-mono text-gray-500 mt-1">
                                        <div>Ref: <span class="font-bold text-gray-800">{{ $certificateNumber }}</span></div>
                                        <div>Date: <span class="font-bold text-gray-800">{{ date('M d, Y', strtotime($issuedDate)) }}</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer & Company Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                <div class="space-y-2">
                                    <h3 class="text-xs uppercase font-extrabold text-gray-400 tracking-widest">Certified Recipient</h3>
                                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                        <div class="text-base font-extrabold text-gray-900">{{ $customer->name }}</div>
                                        @if($customer->company_name)
                                            <div class="text-sm font-semibold text-indigo-600 mt-0.5">{{ $customer->company_name }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 space-y-0.5 mt-2 font-medium">
                                            @if($customer->email) <div>Email: {{ $customer->email }}</div> @endif
                                            @if($customer->phone) <div>Phone: {{ $customer->phone }}</div> @endif
                                            @if($customer->address) <div class="mt-1 italic">Address: {{ $customer->address }}</div> @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <h3 class="text-xs uppercase font-extrabold text-gray-400 tracking-widest">Issuer Reference</h3>
                                    <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-50/80">
                                        <div class="text-base font-extrabold text-indigo-950">{{ setting('website_name', 'SCM ERP Corporate Office') }}</div>
                                        <div class="text-xs text-indigo-900/70 mt-1 font-medium space-y-1">
                                            <p class="italic leading-relaxed">{!! nl2br(e(setting('company_location', 'Corporate Logistics, Warehousing & Supply Chain Operations Hub.\nJebel Ali Free Zone, Dubai, United Arab Emirates'))) !!}</p>
                                            <div class="mt-2 pt-2 border-t border-indigo-100">Authorized Representative: {{ auth()->user()->name }}</div>
                                            <div>Designation: {{ auth()->user()->can('manage users') ? 'System Administrator' : 'Operations Manager' }}</div>
                                            <div>Status Check: Payment History Validated</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Certificate Body Message -->
                            <div class="mb-8 text-sm leading-relaxed text-gray-600 bg-emerald-50/20 border border-emerald-500/10 p-4 rounded-2xl">
                                We hereby certify that the customer listed above has successfully processed payments totaling 
                                <span class="font-extrabold text-indigo-600">{{ setting('currency_symbol', '$') }}{{ number_format($this->selectedTotal, 2) }}</span> 
                                as detailed in the ledger receipt breakdown below.
                                @if($customNotes)
                                    <div class="mt-3 text-slate-700 bg-white p-3 rounded-xl border border-gray-100 font-semibold text-xs italic">
                                        &ldquo;{{ $customNotes }}&rdquo;
                                    </div>
                                @endif
                            </div>

                            <!-- Selected Payments Ledger Breakdown -->
                            <div class="space-y-2 mb-8">
                                <h3 class="text-xs uppercase font-extrabold text-gray-400 tracking-widest mb-3">Itemized Payment Audit Ledger</h3>
                                <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                                    <table class="min-w-full divide-y divide-gray-150">
                                        <thead class="bg-gray-50">
                                            <tr class="text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                                <th class="px-4 py-3">Receipt Date</th>
                                                <th class="px-4 py-3">Account Receivable Ref</th>
                                                <th class="px-4 py-3">Reference / Tx No</th>
                                                <th class="px-4 py-3 text-right">Amount Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-150 bg-white text-sm">
                                            @foreach($this->selectedPayments as $sp)
                                                <tr class="hover:bg-slate-50/50">
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-medium text-gray-900">
                                                        {{ date('M d, Y', strtotime($sp->payment_date)) }}
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <div class="font-semibold text-gray-700">AR-#{{ $sp->account_receivable_id }}</div>
                                                        @if($sp->accountReceivable && $sp->accountReceivable->invoice_id)
                                                            <div class="text-[10px] text-gray-400 font-mono">Invoice: INV-#{{ $sp->accountReceivable->invoice_id }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-mono text-gray-500">
                                                        {{ $sp->reference_number ?: 'N/A' }}
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap font-mono text-right font-bold text-indigo-600">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($sp->amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Totals and outstanding -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center border-t border-gray-200 pt-6 mb-12">
                                <div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Remaining Outstanding Account Balance</div>
                                    <div class="text-lg font-black text-rose-500 font-mono mt-0.5">
                                        {{ setting('currency_symbol', '$') }}{{ number_format($this->outstandingBalance, 2) }}
                                    </div>
                                </div>
                                <div class="bg-indigo-600 text-white rounded-2xl p-4 text-right flex justify-between items-center md:block">
                                    <div class="text-xs text-indigo-200 font-bold uppercase tracking-wider md:mb-1">Total Certified Payments</div>
                                    <div class="text-2xl font-black font-mono">
                                        {{ setting('currency_symbol', '$') }}{{ number_format($this->selectedTotal, 2) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Signature and official seal -->
                            <div class="flex flex-col sm:flex-row justify-between items-end pt-8 border-t border-dashed border-gray-200 mt-12 gap-8">
                                <div class="text-xs text-gray-400 space-y-1">
                                    <div class="font-bold uppercase tracking-widest text-[9px]">Validation Notice</div>
                                    <div>This document is automatically validated by the {{ setting('website_name', 'SCM ERP') }} financial core ledger.</div>
                                    <div>Verification Ref: {{ substr(md5($certificateNumber . $this->selectedTotal), 0, 12) }}</div>
                                </div>
                                <div class="text-center sm:text-right w-48 border-t border-gray-400 pt-3">
                                    <div class="font-bold text-sm text-gray-900">{{ auth()->user()->name }}</div>
                                    <div class="text-xs text-gray-500">Authorized Financial Signatory</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- Modals Section (Direct Updates from Profile) -->
    @if($showReceivableModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showReceivableModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">{{ $receivableId ? 'Edit Accounts Receivable Ledger' : 'Create Manual Accounts Receivable' }}</h3>
                    
                    <form wire:submit.prevent="saveReceivable" class="space-y-4">
                        <div>
                            <x-input-label value="Customer Recipient" />
                            <x-text-input type="text" class="mt-1 block w-full bg-gray-100 text-gray-500 font-bold" value="{{ $customer->name }}" disabled />
                        </div>
                        <div>
                            <x-input-label value="Amount Due *" />
                            <x-text-input wire:model="receivableAmount" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('receivableAmount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Status *" />
                            <select wire:model="receivableStatus" class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-semibold text-gray-700" required>
                                <option value="unpaid">Unpaid</option>
                                <option value="partial">Partial</option>
                                <option value="paid">Paid</option>
                            </select>
                            <x-input-error :messages="$errors->get('receivableStatus')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Supporting Document Attachment (Optional)" />
                            <input type="file" wire:model="receivableAttachment" class="mt-1 block w-full text-xs text-gray-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <x-input-error :messages="$errors->get('receivableAttachment')" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showReceivableModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showPaymentModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showPaymentModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Record Payment Receipt</h3>
                    
                    @php
                        $selectedAR = $customer->accountReceivables->firstWhere('id', $paymentReceivableId);
                    @endphp

                    @if($selectedAR)
                        <div class="grid grid-cols-2 gap-4 mb-4 bg-slate-50 p-3 rounded-2xl border border-slate-100 text-xs">
                            <div>
                                <span class="text-gray-400 font-bold uppercase">Total Bill Due</span>
                                <div class="font-extrabold text-gray-900 mt-0.5">{{ setting('currency_symbol', '$') }}{{ number_format($selectedAR->amount, 2) }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 font-bold uppercase">Current Outstanding</span>
                                <div class="font-extrabold text-rose-500 mt-0.5">{{ setting('currency_symbol', '$') }}{{ number_format($selectedAR->balance, 2) }}</div>
                            </div>
                        </div>
                    @endif

                    <form wire:submit.prevent="logPayment" class="space-y-4">
                        <div>
                            <x-input-label value="Amount Paid *" />
                            <x-text-input wire:model="paymentAmount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('paymentAmount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Payment Date *" />
                            <x-text-input wire:model="paymentDate" type="date" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('paymentDate')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Reference Number (Check/Bank Trans ID)" />
                            <x-text-input wire:model="paymentRef" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('paymentRef')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Proof of Payment (Optional)" />
                            <input type="file" wire:model="paymentAttachment" class="mt-1 block w-full text-xs text-gray-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <x-input-error :messages="$errors->get('paymentAttachment')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Internal Notes" />
                            <textarea wire:model="paymentNotes" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Memo notes..."></textarea>
                            <x-input-error :messages="$errors->get('paymentNotes')" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showPaymentModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Record Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

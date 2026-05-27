<?php

use Livewire\Volt\Component;
use App\Models\Customer;
use App\Models\PaymentLog;
use App\Models\AccountReceivable;

new class extends Component {
    public $customers = [];
    public $selectedCustomerId = '';
    public $payments = [];
    public $selectedPaymentIds = [];
    
    // Certificate Metadata
    public $certificateNumber = '';
    public $issuedDate = '';
    public $customNotes = '';
    
    // View state
    public $previewMode = false;
    
    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->customers = Customer::orderBy('name')->get();
        $this->issuedDate = date('Y-m-d');
        $this->generateCertNumber();
    }
    
    public function generateCertNumber()
    {
        $this->certificateNumber = 'CERT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
    
    public function updatedSelectedCustomerId($value)
    {
        $this->payments = [];
        $this->selectedPaymentIds = [];
        $this->previewMode = false;
        
        if ($value) {
            // Find all payment logs linked to receivables belonging to this customer
            $this->payments = PaymentLog::whereHas('accountReceivable', function ($query) use ($value) {
                $query->where('customer_id', $value);
            })
            ->with('accountReceivable.invoice')
            ->orderBy('payment_date', 'desc')
            ->get();
        }
    }
    
    public function selectAllPayments()
    {
        $this->selectedPaymentIds = collect($this->payments)->pluck('id')->map(fn($id) => (string)$id)->toArray();
    }
    
    public function deselectAllPayments()
    {
        $this->selectedPaymentIds = [];
    }
    
    public function getSelectedPaymentsProperty()
    {
        return PaymentLog::whereIn('id', $this->selectedPaymentIds)
            ->with('accountReceivable.invoice')
            ->orderBy('payment_date', 'asc')
            ->get();
    }
    
    public function getCustomerProperty()
    {
        return Customer::find($this->selectedCustomerId);
    }
    
    public function getSelectedTotalProperty()
    {
        return PaymentLog::whereIn('id', $this->selectedPaymentIds)->sum('amount');
    }

    public function getCustomerBalanceProperty()
    {
        if (!$this->selectedCustomerId) return 0;
        return AccountReceivable::where('customer_id', $this->selectedCustomerId)->get()->sum('balance');
    }
    
    public function togglePreview()
    {
        $this->validate([
            'selectedCustomerId' => 'required',
            'certificateNumber' => 'required|string',
            'issuedDate' => 'required|date',
            'selectedPaymentIds' => 'required|array|min:1',
        ], [
            'selectedPaymentIds.required' => 'Please select at least one payment to include in the certificate.',
            'selectedPaymentIds.min' => 'Please select at least one payment to include in the certificate.',
        ]);
        
        $this->previewMode = !$this->previewMode;
    }
};

?>

<div>
    @if($previewMode)
        <style>
            @media print {
                body {
                    background: white !important;
                    color: black !important;
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
                {{ __('Payment Certificate Generator') }}
            </h2>
            <a href="{{ url('/finance/receivables') }}" class="inline-flex items-center gap-2 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors bg-indigo-50 px-3.5 py-2 rounded-xl">
                &larr; Back to Receivables
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(!$previewMode)
                <!-- Form & Selection View -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 no-print">
                    
                    <!-- Left: Filters & Configuration -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Step 1: Customer Selection -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">1. Select Target Customer</h3>
                            <p class="text-xs text-gray-400 mb-4">Select the customer to fetch their payment ledger history.</p>

                            <select wire:model.live="selectedCustomerId" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Choose Customer --</option>
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->id }}">{{ $cust->name }} ({{ $cust->company_name ?? 'Individual' }})</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Step 2: Select Payments -->
                        @if($selectedCustomerId)
                            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900">2. Select Payments to Include</h3>
                                        <p class="text-xs text-gray-400 mt-0.5">Use checkboxes to compile customized payments onto a single certificate.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="selectAllPayments" class="px-2.5 py-1 text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg">Select All</button>
                                        <button type="button" wire:click="deselectAllPayments" class="px-2.5 py-1 text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">Deselect All</button>
                                    </div>
                                </div>

                                @error('selectedPaymentIds')
                                    <div class="p-3 mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl font-medium">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-150">
                                        <thead>
                                            <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                                <th class="px-4 py-3 rounded-l-xl text-center w-12">Select</th>
                                                <th class="px-4 py-3">Receipt Date</th>
                                                <th class="px-4 py-3">Reference / Tx No</th>
                                                <th class="px-4 py-3">AR Ref</th>
                                                <th class="px-4 py-3 text-right rounded-r-xl">Amount Received</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @forelse($payments as $pmt)
                                                <tr class="hover:bg-gray-50/30 transition-colors">
                                                    <td class="px-4 py-3.5 text-center">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model.live="selectedPaymentIds" 
                                                            value="{{ $pmt->id }}"
                                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        >
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-sm text-gray-500 font-semibold">
                                                        {{ date('M d, Y', strtotime($pmt->payment_date)) }}
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                                        <div class="text-sm font-semibold text-gray-900">{{ $pmt->reference_number ?: 'N/A' }}</div>
                                                        @if($pmt->notes)
                                                            <div class="text-xs text-gray-400 truncate max-w-[200px]" title="{{ $pmt->notes }}">{{ $pmt->notes }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-sm text-gray-500 font-mono">
                                                        AR-#{{ $pmt->account_receivable_id }}
                                                        @if($pmt->accountReceivable && $pmt->accountReceivable->invoice_id)
                                                            <span class="text-xs text-gray-400 block">INV-#{{ $pmt->accountReceivable->invoice_id }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3.5 whitespace-nowrap text-sm font-mono text-right font-extrabold text-gray-900">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($pmt->amount, 2) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                                        No payments found for this customer. Please log customer payments in the Accounts Receivable menu first.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-50 border border-dashed border-gray-350 rounded-3xl p-12 text-center text-gray-500 font-medium">
                                👈 Select a customer in the filter list to pull historical received payments.
                            </div>
                        @endif
                    </div>

                    <!-- Right: Metadata & Summary Panel -->
                    <div class="space-y-6">
                        <!-- Step 3: Certificate Details -->
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 space-y-4">
                            <h3 class="text-lg font-bold text-gray-900">3. Certificate Parameters</h3>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase">Certificate Number *</label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input type="text" wire:model="certificateNumber" class="block w-full rounded-l-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-semibold text-gray-700">
                                    <button type="button" wire:click="generateCertNumber" class="inline-flex items-center px-3 rounded-r-xl border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold hover:bg-gray-100">Regen</button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase">Certificate Issue Date *</label>
                                <input type="date" wire:model="issuedDate" class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-semibold text-gray-700">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase">Personalized Memo (To Customer)</label>
                                <textarea wire:model="customNotes" rows="3" class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" placeholder="e.g. Thank you for your continued trust. All outstanding payments have been settled..."></textarea>
                            </div>
                        </div>

                        <!-- Summary Block -->
                        @if($selectedCustomerId)
                            <div class="bg-gradient-to-br from-indigo-900 to-indigo-950 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden border border-indigo-500/20">
                                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>

                                <h3 class="text-lg font-extrabold tracking-tight mb-4">Certificate Summary</h3>
                                
                                <div class="space-y-4 border-b border-white/10 pb-4 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-indigo-200/80">Recipient:</span>
                                        <span class="font-bold text-white">{{ $this->customer->name }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-indigo-200/80">Selected Payments:</span>
                                        <span class="font-bold text-white">{{ count($selectedPaymentIds) }} receipt(s)</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-indigo-200/80">Outstanding Balance:</span>
                                        <span class="font-bold text-rose-300">{{ setting('currency_symbol', '$') }}{{ number_format($this->customerBalance, 2) }}</span>
                                    </div>
                                </div>

                                <div class="space-y-1 mb-6">
                                    <span class="text-xs text-indigo-300 uppercase font-bold tracking-wider">Total Certified Amount</span>
                                    <h2 class="text-3xl font-black text-emerald-400 tracking-tight">{{ setting('currency_symbol', '$') }}{{ number_format($this->selectedTotal, 2) }}</h2>
                                </div>

                                <button 
                                    type="button" 
                                    wire:click="togglePreview"
                                    class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-md hover:bg-indigo-500 transition-all duration-200 border border-indigo-400/20"
                                >
                                    Generate & Preview
                                </button>
                            </div>
                        @endif
                    </div>

                </div>
            @else
                <!-- Printable Certificate Preview Mode -->
                <div class="space-y-6">
                    <!-- Actions Menu (Hides on browser printing) -->
                    <div class="flex items-center justify-between no-print bg-white p-4 rounded-2xl shadow-sm border border-gray-150">
                        <button type="button" wire:click="togglePreview" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs transition-colors">
                            &larr; Back to Parameters
                        </button>

                        <div class="flex items-center gap-3">
                            <button onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-md transition-all duration-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print / Export to PDF
                            </button>
                        </div>
                    </div>

                    <!-- Certificate Box itself -->
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
                                        <div class="text-base font-extrabold text-gray-900">{{ $this->customer->name }}</div>
                                        @if($this->customer->company_name)
                                            <div class="text-sm font-semibold text-indigo-600 mt-0.5">{{ $this->customer->company_name }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 space-y-0.5 mt-2 font-medium">
                                            @if($this->customer->email) <div>Email: {{ $this->customer->email }}</div> @endif
                                            @if($this->customer->phone) <div>Phone: {{ $this->customer->phone }}</div> @endif
                                            @if($this->customer->address) <div class="mt-1 italic">Address: {{ $this->customer->address }}</div> @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <h3 class="text-xs uppercase font-extrabold text-gray-400 tracking-widest">Issuer Reference</h3>
                                    <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-50/80">
                                        <div class="text-base font-extrabold text-indigo-950">{{ setting('website_name', 'SCM ERP Corporate Office') }}</div>
                                        <div class="text-xs text-indigo-900/70 mt-1 font-medium space-y-1">
                                            <div>Authorized Representative: {{ auth()->user()->name }}</div>
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
                                        {{ setting('currency_symbol', '$') }}{{ number_format($this->customerBalance, 2) }}
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
                                    <div>This document is automatically validated by the SCM ERP financial core ledger.</div>
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
</div>

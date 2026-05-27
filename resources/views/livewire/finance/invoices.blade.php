<?php

use function Livewire\Volt\{state, mount};
use App\Models\Invoice;
use App\Models\Project;

state([
    'invoices' => [],
    'projects' => [],
    
    // View Modal State
    'showInvoiceModal' => false,
    'selectedInvoice' => null,

    // Edit Modal State
    'showEditModal' => false,
    'editInvoiceId' => null,
    'editAmount' => 0,
    'editStatus' => 'unpaid',
    'editProjectId' => '',
]);

mount(function () {
    if (!auth()->user()->can('view receivables')) abort(403);
    $this->loadData();
});

$loadData = function () {
    $this->invoices = Invoice::with(['salesOrder.customer', 'salesOrder.items.product', 'project'])
        ->latest()
        ->get();
    $this->projects = Project::orderBy('name')->get();
};

$viewInvoice = function ($id) {
    $this->selectedInvoice = Invoice::with(['salesOrder.customer', 'salesOrder.items.product', 'project'])->findOrFail($id);
    $this->showInvoiceModal = true;
};

$changeStatus = function ($id) {
    $invoice = Invoice::findOrFail($id);
    $invoice->status = $invoice->status === 'paid' ? 'unpaid' : 'paid';
    $invoice->save();
    $this->loadData();
    session()->flash('success', 'Invoice status toggled successfully.');
};

$deleteInvoice = function ($id) {
    $invoice = Invoice::findOrFail($id);
    $invoice->delete();
    $this->loadData();
    session()->flash('success', 'Invoice deleted successfully.');
};

$editInvoice = function ($id) {
    $invoice = Invoice::findOrFail($id);
    $this->editInvoiceId = $invoice->id;
    $this->editAmount = $invoice->amount;
    $this->editStatus = $invoice->status;
    $this->editProjectId = $invoice->project_id;
    $this->showEditModal = true;
};

$updateInvoice = function () {
    $this->validate([
        'editAmount' => 'required|numeric|min:0',
        'editStatus' => 'required|string|in:issued,paid,unpaid',
    ]);

    $invoice = Invoice::findOrFail($this->editInvoiceId);
    $invoice->update([
        'amount' => $this->editAmount,
        'status' => $this->editStatus,
        'project_id' => $this->editProjectId ?: null,
    ]);

    $this->showEditModal = false;
    $this->loadData();
    session()->flash('success', 'Invoice details updated successfully.');
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-emerald-600 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Invoices &amp; Billing</h1>
            <p class="text-xs text-gray-500 mt-1">Manage outbound customer billing sheets, update payments, tag project costs, and export corporate invoices.</p>
        </div>
    </div>

    <!-- Financial KPI Summary Cards -->
    @php
        $totalInvoiced = $invoices->sum('amount');
        $totalPaid = $invoices->where('status', 'paid')->sum('amount');
        $totalUnpaid = $invoices->whereIn('status', ['unpaid', 'issued'])->sum('amount');
        $paidCount = $invoices->where('status', 'paid')->count();
        $totalCount = $invoices->count();
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Total Billing Volume</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">${{ number_format($totalInvoiced, 2) }}</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Across {{ $totalCount }} generated sheets</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Paid Accounts Receipts</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">${{ number_format($totalPaid, 2) }}</h3>
            <span class="text-[10px] text-emerald-600 font-bold block mt-1">({{ $paidCount }} of {{ $totalCount }} paid)</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Unpaid Outstanding Balance</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">${{ number_format($totalUnpaid, 2) }}</h3>
            <span class="text-[10px] text-amber-600 font-bold block mt-1">Pending general ledger collections</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Invoice Liquidity Rate</span>
            @php
                $rate = $totalInvoiced > 0 ? ($totalPaid / $totalInvoiced) * 100 : 0;
            @endphp
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ number_format($rate, 1) }}%</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Percentage of funds successfully settled</span>
        </div>
    </div>

    <!-- Main Invoices Table Card -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-150">
                <thead>
                    <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                        <th class="px-6 py-3.5 rounded-l-2xl">Invoice ID</th>
                        <th class="px-6 py-3.5">Customer Client</th>
                        <th class="px-6 py-3.5">Associated Project</th>
                        <th class="px-6 py-3.5 text-right">Billing Amount</th>
                        <th class="px-6 py-3.5">Settlement Status</th>
                        <th class="px-6 py-3.5">Date Issued</th>
                        <th class="px-6 py-3.5 rounded-r-2xl text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-sm">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-gray-50/30 transition-colors">
                            <!-- Invoice ID -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-slate-900">
                                INV-#{{ $invoice->id }}
                            </td>
                            <!-- Customer Client -->
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                                @if($invoice->salesOrder && $invoice->salesOrder->customer)
                                    <div class="font-bold text-gray-900">{{ $invoice->salesOrder->customer->name }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">Ref SO-#{{ $invoice->sales_order_id }}</div>
                                @else
                                    <span class="text-gray-400 italic">No Customer Linked</span>
                                @endif
                            </td>
                            <!-- Associated Project -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($invoice->project)
                                    <a href="{{ route('projects.show', $invoice->project->id) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-0.5 rounded-lg border border-indigo-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                        {{ $invoice->project->code }}
                                    </a>
                                @else
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-100 px-2 py-0.5 rounded-md">Internal SCM</span>
                                @endif
                            </td>
                            <!-- Amount -->
                            <td class="px-6 py-4 whitespace-nowrap text-right font-black text-gray-900 font-mono">
                                ${{ number_format($invoice->amount, 2) }}
                            </td>
                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $badge = 'bg-yellow-50 text-yellow-800 border-yellow-250';
                                    if ($invoice->status === 'paid') $badge = 'bg-emerald-50 text-emerald-800 border-emerald-250';
                                    elseif ($invoice->status === 'unpaid') $badge = 'bg-red-50 text-red-800 border-red-250';
                                @endphp
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-lg border {{ $badge }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <!-- Date Issued -->
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 font-medium">
                                {{ $invoice->created_at->format('M d, Y H:i') }}
                            </td>
                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-bold">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="viewInvoice({{ $invoice->id }})" class="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors" title="View details and print">
                                        <!-- View/Eye Icon -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button wire:click="editInvoice({{ $invoice->id }})" class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-lg transition-colors" title="Edit invoice details">
                                        <!-- Edit/Pencil Icon -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button wire:click="changeStatus({{ $invoice->id }})" class="p-1.5 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition-colors" title="Toggle paid/unpaid status">
                                        <!-- Toggle/Exchange Icon -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                    </button>
                                    <button wire:click="deleteInvoice({{ $invoice->id }})" onclick="confirm('Are you sure you want to permanently delete this billing sheet?') || event.stopImmediatePropagation()" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors" title="Delete invoice">
                                        <!-- Trash/Delete Icon -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400 italic text-sm">No billing invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Invoice Modal -->
    @if($showEditModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showEditModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Edit Invoice Details</h3>

                    <form wire:submit.prevent="updateInvoice" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Billing Amount ($) *" />
                            <x-text-input wire:model="editAmount" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs" required />
                            <x-input-error :messages="$errors->get('editAmount')" class="mt-1" />
                        </div>
                        
                        <div>
                            <x-input-label value="Settlement Status *" />
                            <select wire:model="editStatus" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                <option value="unpaid">Unpaid / Open</option>
                                <option value="issued">Issued / Pending</option>
                                <option value="paid">Paid &amp; Settled</option>
                            </select>
                            <x-input-error :messages="$errors->get('editStatus')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Associate with Project Contract" />
                            <select wire:model="editProjectId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold">
                                <option value="">-- No Project Link (Internal) --</option>
                                @foreach($projects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->code }} - {{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Invoice Details & printable A4 Letterhead Modal -->
    @if($showInvoiceModal)
        <style>
            @media print {
                body {
                    background: white !important;
                    color: black !important;
                }
                body * {
                    visibility: hidden;
                }
                #printable-invoice-area, #printable-invoice-area * {
                    visibility: visible !important;
                }
                #printable-invoice-area {
                    position: fixed !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    z-index: 9999999 !important;
                    background: white !important;
                    color: black !important;
                    padding: 2cm !important;
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
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-60 no-print" wire:click="$set('showInvoiceModal', false)"></div>
                
                <div class="inline-block w-full max-w-3xl p-8 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl relative z-50 border border-slate-100">
                    
                    <!-- View Modal Header (No Print) -->
                    <div class="flex justify-between items-center border-b border-slate-100 pb-4 mb-6 no-print">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Invoice Ledger sheet</h3>
                            <p class="text-xs text-slate-400">View or download standard billing sheet.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print / PDF
                            </button>
                            <button wire:click="$set('showInvoiceModal', false)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    @if($selectedInvoice)
                        <!-- PRINT READY AREA (A4 Standard corporate letterhead layout) -->
                        <div id="printable-invoice-area" class="bg-white p-2 rounded-2xl select-text">
                            <!-- Corporate Letterhead Header -->
                            <div class="flex justify-between items-start border-b border-slate-150 pb-6 mb-8">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center text-white text-lg font-bold shadow-md">
                                            @if(setting('website_logo'))
                                                <img src="{{ setting('website_logo') }}" class="w-6 h-6 object-contain" alt="">
                                            @else
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <span class="text-lg font-black text-slate-800 tracking-tight">{{ setting('website_name', 'SCM ERP System') }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-2.5 max-w-xs leading-relaxed">
                                        Corporate Logistics, Warehousing &amp; Supply Chain Operations Hub.<br>
                                        Jebel Ali Free Zone, Dubai, United Arab Emirates
                                    </p>
                                </div>
                                <div class="text-right">
                                    <h2 class="text-3xl font-black text-indigo-600 font-mono uppercase tracking-tight">INVOICE</h2>
                                    <div class="text-xs text-slate-500 font-mono font-bold mt-1">INV-#{{ $selectedInvoice->id }}</div>
                                    <div class="text-[10px] text-slate-400 font-medium font-mono mt-0.5">Date: {{ $selectedInvoice->created_at->format('M d, Y') }}</div>
                                </div>
                            </div>

                            <!-- Billing Info -->
                            <div class="grid grid-cols-2 gap-8 mb-8 text-xs font-semibold">
                                <div class="space-y-1">
                                    <h4 class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Client Recipient</h4>
                                    <p class="text-sm font-black text-slate-850">{{ $selectedInvoice->salesOrder->customer->name ?? 'N/A' }}</p>
                                    @if($selectedInvoice->salesOrder && $selectedInvoice->salesOrder->customer && $selectedInvoice->salesOrder->customer->company_name)
                                        <p class="text-indigo-600 font-bold">{{ $selectedInvoice->salesOrder->customer->company_name }}</p>
                                    @endif
                                    <p class="text-slate-500 leading-relaxed font-normal">
                                        Email: {{ $selectedInvoice->salesOrder->customer->email ?? 'N/A' }}<br>
                                        Phone: {{ $selectedInvoice->salesOrder->customer->phone ?? 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-right space-y-1">
                                    <h4 class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Operational Tracking</h4>
                                    <p class="text-slate-700 font-bold">Sales Order: <span class="font-mono text-slate-900 font-extrabold">SO-#{{ $selectedInvoice->sales_order_id }}</span></p>
                                    <p class="text-slate-700 font-bold">Status: 
                                        <span class="font-black uppercase {{ $selectedInvoice->status === 'paid' ? 'text-emerald-600' : 'text-amber-600' }}">
                                            {{ $selectedInvoice->status }}
                                        </span>
                                    </p>
                                    @if($selectedInvoice->project)
                                        <p class="text-slate-700 font-bold">Project Tag: <span class="font-mono text-indigo-600 font-extrabold">{{ $selectedInvoice->project->code }}</span></p>
                                    @endif
                                </div>
                            </div>

                            <!-- Line items Table -->
                            <div class="mb-8">
                                <table class="min-w-full divide-y divide-slate-150 text-xs select-text">
                                    <thead>
                                        <tr class="text-left font-bold text-slate-400 bg-slate-50">
                                            <th class="px-4 py-3 rounded-l-xl">Line Item &amp; Product Specification</th>
                                            <th class="px-4 py-3 text-center">Quantity</th>
                                            <th class="px-4 py-3 text-right">Unit Price</th>
                                            <th class="px-4 py-3 rounded-r-xl text-right">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium">
                                        @if($selectedInvoice->salesOrder)
                                            @foreach($selectedInvoice->salesOrder->items as $item)
                                                <tr>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="font-black text-slate-800 text-xs">{{ $item->product->name }}</div>
                                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">SKU: {{ $item->product->sku }}</div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-bold text-slate-650">
                                                        {{ number_format($item->quantity, 0) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-mono text-slate-650">
                                                        ${{ number_format($item->unit_price, 2) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-black font-mono text-slate-800">
                                                        ${{ number_format($item->total_price, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Grand total sheet -->
                            <div class="flex justify-between items-start border-t border-slate-150 pt-6">
                                <div class="max-w-md">
                                    <h4 class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider">Payment Verification Terms</h4>
                                    <p class="text-[9.5px] text-slate-400 mt-1 max-w-sm leading-relaxed font-normal">
                                        This document represents a legally verified commercial billing invoice generated automatically by the SCM ERP general ledger. All shipments are subject to standard warehouse audits and delivery dispatch sheets.
                                    </p>
                                </div>
                                <div class="text-right w-64">
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1">
                                        <span>Subtotal:</span>
                                        <span class="font-mono text-slate-700">${{ number_format($selectedInvoice->amount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1 border-b border-slate-100">
                                        <span>Tax (0% VAT):</span>
                                        <span class="font-mono text-slate-700">$0.00</span>
                                    </div>
                                    <div class="flex justify-between text-base font-black text-slate-800 pt-3">
                                        <span class="text-slate-500 font-normal">Total Balance:</span>
                                        <span class="font-mono text-indigo-600 text-lg">${{ number_format($selectedInvoice->amount, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Signature and Verification hashes -->
                            <div class="grid grid-cols-2 gap-8 border-t border-slate-150 pt-10 mt-12 text-[9px] font-mono text-slate-400">
                                <div>
                                    <p class="font-bold text-slate-500 uppercase tracking-wider mb-8">Authorized Seal &amp; Audit Approval</p>
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500">
                                            <!-- Checkmark Shield Icon -->
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-500 uppercase block">ERP Verification Token</span>
                                            <span class="text-[8px] font-mono tracking-tight">{{ hash('sha256', $selectedInvoice->id . '-' . $selectedInvoice->amount . '-' . $selectedInvoice->created_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex flex-col justify-end">
                                    <div class="border-b border-slate-200 w-48 ml-auto mb-1"></div>
                                    <span class="font-bold text-slate-500 uppercase block mr-4">Financial Ledger Officer</span>
                                    <span class="text-[8.5px] block mr-4">SCM ERP Operations Core</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

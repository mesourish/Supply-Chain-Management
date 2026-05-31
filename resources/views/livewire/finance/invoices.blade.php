<?php

use function Livewire\Volt\{state, mount, updated};
use App\Models\Invoice;
use App\Models\InvoiceItem;

use App\Models\Product;

state([
    'invoices' => [],

    'customers' => [],
    'products' => [],
    
    // View Modal State
    'showInvoiceModal' => false,
    'selectedInvoice' => null,

    // Edit Modal State
    'showEditModal' => false,
    'editInvoiceId' => null,
    'editAmount' => 0,
    'editStatus' => 'unpaid',

    'editTaxAmount' => 0,
    'editShippingAmount' => 0,
    'editDescription' => '',
    'editGstType' => 'exclusive',
    'editGstPercentage' => 0,
    'editItems' => [],
    'isSalesOrderInvoice' => false,

    // Create Manual Invoice State
    'showCreateModal' => false,
    'newAmount' => 0,
    'newTaxAmount' => 0,
    'newShippingAmount' => 0,
    'newCustomerId' => '',

    'newIssueDate' => '',
    'newDueDate' => '',
    'newDescription' => '',
    'newGstType' => 'exclusive',
    'newGstPercentage' => 0,
    'newApplyGst' => false,
    'newItems' => [],
]);

mount(function () {
    if (!auth()->user()->can('view receivables')) abort(403);
    $this->newGstPercentage = (float)setting('default_gst_percentage', '18');
    $this->newGstType = setting('default_gst_type', 'exclusive');
    $this->loadData();
});

$loadData = function () {
    $this->invoices = Invoice::with(['salesOrder.customer', 'salesOrder.items.product', 'customer', 'items.product'])
        ->latest()
        ->get();
    $this->customers = App\Models\Customer::orderBy('name')->get();
    $this->products = Product::orderBy('name')->get();
};

$resetCreateForm = function () {
    $this->newAmount = 0;
    $this->newTaxAmount = 0;
    $this->newShippingAmount = 0;
    $this->newCustomerId = '';

    $this->newIssueDate = now()->toDateString();
    $this->newDueDate = now()->addDays(30)->toDateString();
    $this->newDescription = '';
    $this->newApplyGst = false;
    $this->newGstType = setting('default_gst_type', 'exclusive');
    $this->newGstPercentage = (float)setting('default_gst_percentage', '18');
    $this->newItems = [
        ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
    ];
};

$openCreateModal = function () {
    $this->resetCreateForm();
    $this->showCreateModal = true;
};

// Item line methods
$addItemLine = function () {
    $this->newItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false];
};
$addBlankLine = function () {
    $this->newItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true];
};
$removeItemLine = function ($index) {
    if (count($this->newItems) > 1) {
        unset($this->newItems[$index]);
        $this->newItems = array_values($this->newItems);
    }
};

$addEditItemLine = function () {
    $this->editItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false];
};
$addEditBlankLine = function () {
    $this->editItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true];
};
$removeEditItemLine = function ($index) {
    if (count($this->editItems) > 1) {
        unset($this->editItems[$index]);
        $this->editItems = array_values($this->editItems);
    }
};

updated(['newItems.*.product_id', 'editItems.*.product_id'], function ($value, $key) {
    $parts = explode('.', $key);
    $arrayName = $parts[0]; // 'newItems' or 'editItems'
    $index = $parts[1] ?? null;
    
    if ($index !== null && isset($this->{$arrayName}[$index])) {
        $productId = $value;
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $this->{$arrayName}[$index]['unit_price'] = $product->unit_price;
            }
        }
    }
});

// Calculations
$calculateTotals = function ($items, $applyGst, $gstType, $gstPct, $shipping) {
    $subtotal = 0;
    foreach($items as $item) {
        $subtotal += ((float)$item['quantity'] * (float)$item['unit_price']);
    }
    
    $tax = 0;
    if ($applyGst) {
        if ($gstType === 'inclusive') {
            $tax = $subtotal - ($subtotal / (1 + ($gstPct / 100)));
            $subtotal = $subtotal - $tax;
        } else {
            $tax = $subtotal * ($gstPct / 100);
        }
    }
    
    $total = $subtotal + $tax + (float)$shipping;
    return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total];
};

$viewInvoice = function ($id) {
    $this->selectedInvoice = Invoice::with(['salesOrder.customer', 'salesOrder.items.product', 'customer', 'items.product'])->findOrFail($id);
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
    $invoice = Invoice::with('items')->findOrFail($id);
    $this->editInvoiceId = $invoice->id;
    $this->editAmount = $invoice->amount;
    $this->editStatus = $invoice->status;

    $this->editTaxAmount = $invoice->tax_amount;
    $this->editShippingAmount = $invoice->shipping_amount;
    $this->editDescription = $invoice->description;
    $this->editGstType = $invoice->gst_type;
    $this->editGstPercentage = $invoice->gst_percentage;
    $this->isSalesOrderInvoice = $invoice->sales_order_id !== null;
    
    // We only populate editItems for manual invoices (sales_order_id is null)
    $this->editItems = [];
    if (!$this->isSalesOrderInvoice) {
        foreach ($invoice->items as $item) {
            $this->editItems[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'is_blank' => $item->product_id === null,
            ];
        }
        
        // Handle legacy manual invoices without items
        if (empty($this->editItems)) {
            if ($invoice->amount > 0) {
                $subtotal = $invoice->amount - $invoice->tax_amount - $invoice->shipping_amount;
                $this->editItems[] = [
                    'product_id' => '',
                    'description' => $invoice->description ?: 'Legacy Manual Invoice Amount',
                    'quantity' => 1,
                    'unit_price' => max(0, $subtotal),
                    'is_blank' => true,
                ];
            } else {
                $this->editItems[] = [
                    'product_id' => '',
                    'description' => '',
                    'quantity' => 1,
                    'unit_price' => 0.00,
                    'is_blank' => false
                ];
            }
        }
    }
    
    $this->showEditModal = true;
};

$updateInvoice = function () {
    $this->validate([
        'editStatus' => 'required|string|in:issued,paid,unpaid',
    ]);

    $invoice = Invoice::findOrFail($this->editInvoiceId);
    
    if (!$this->isSalesOrderInvoice) {
        // Manual invoice: recalculate amounts and save items
        $this->validate([
            'editItems.*.quantity' => 'required|numeric|min:0.01',
            'editItems.*.unit_price' => 'required|numeric|min:0',
        ]);
        
        foreach ($this->editItems as $idx => $item) {
            if (empty($item['is_blank']) && empty($item['product_id'])) {
                $this->addError("editItems.{$idx}.product_id", "Product is required.");
            }
            if (!empty($item['is_blank']) && empty($item['description'])) {
                $this->addError("editItems.{$idx}.description", "Description is required for custom lines.");
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) return;

        $totals = $this->calculateTotals($this->editItems, $this->editGstPercentage > 0, $this->editGstType, $this->editGstPercentage, $this->editShippingAmount ?: 0);
        
        $invoice->update([
            'amount' => $totals['total'],
            'tax_amount' => $totals['tax'],
            'shipping_amount' => $this->editShippingAmount ?: 0,
            'status' => $this->editStatus,

            'description' => $this->editDescription,
            'gst_type' => $this->editGstType,
            'gst_percentage' => $this->editGstPercentage,
        ]);
        
        // Update Items
        $invoice->items()->delete();
        foreach ($this->editItems as $item) {
            $invoice->items()->create([
                'product_id' => empty($item['is_blank']) ? $item['product_id'] : null,
                'description' => $item['is_blank'] ? $item['description'] : null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }
    } else {
        // Sales order invoice: only update allowed non-financial fields
        $invoice->update([
            'status' => $this->editStatus,

            'description' => $this->editDescription,
        ]);
    }

    $this->showEditModal = false;
    $this->loadData();
    session()->flash('success', 'Invoice details updated successfully.');
};

$createManualInvoice = function () {
    $this->validate([
        'newCustomerId' => 'required|exists:customers,id',
        'newIssueDate' => 'required|date',
        'newItems.*.quantity' => 'required|numeric|min:0.01',
        'newItems.*.unit_price' => 'required|numeric|min:0',
    ]);
    
    foreach ($this->newItems as $idx => $item) {
        if (empty($item['is_blank']) && empty($item['product_id'])) {
            $this->addError("newItems.{$idx}.product_id", "Product is required.");
        }
        if (!empty($item['is_blank']) && empty($item['description'])) {
            $this->addError("newItems.{$idx}.description", "Description is required for custom lines.");
        }
    }
    if ($this->getErrorBag()->isNotEmpty()) return;
    
    $totals = $this->calculateTotals($this->newItems, $this->newApplyGst, $this->newGstType, $this->newGstPercentage, $this->newShippingAmount ?: 0);

    $invoice = Invoice::create([
        'customer_id' => $this->newCustomerId,

        'amount' => $totals['total'],
        'tax_amount' => $totals['tax'],
        'shipping_amount' => $this->newShippingAmount ?: 0,
        'gst_type' => $this->newApplyGst ? $this->newGstType : 'exclusive',
        'gst_percentage' => $this->newApplyGst ? $this->newGstPercentage : 0,
        'issue_date' => $this->newIssueDate,
        'due_date' => $this->newDueDate ?: null,
        'description' => $this->newDescription,
        'status' => 'issued',
    ]);
    
    foreach ($this->newItems as $item) {
        $invoice->items()->create([
            'product_id' => empty($item['is_blank']) ? $item['product_id'] : null,
            'description' => $item['is_blank'] ? $item['description'] : null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
        ]);
    }

    $this->showCreateModal = false;
    $this->loadData();
    session()->flash('success', 'Manual invoice created successfully.');
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
            <p class="text-xs text-gray-500 mt-1">Manage outbound customer billing sheets, update payments, and export corporate invoices.</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
            + Create Manual Invoice
        </button>
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
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalInvoiced, 2) }}</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Across {{ $totalCount }} generated sheets</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Paid Accounts Receipts</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalPaid, 2) }}</h3>
            <span class="text-[10px] text-emerald-600 font-bold block mt-1">({{ $paidCount }} of {{ $totalCount }} paid)</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Unpaid Outstanding Balance</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalUnpaid, 2) }}</h3>
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
                                @elseif($invoice->customer)
                                    <div class="font-bold text-gray-900">{{ $invoice->customer->name }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">Manual Invoice</div>
                                @else
                                    <span class="text-gray-400 italic">No Customer Linked</span>
                                @endif
                            </td>

                            <!-- Amount -->
                            <td class="px-6 py-4 whitespace-nowrap text-right font-black text-gray-900 font-mono">
                                {{ setting('currency_symbol', '$') }}{{ number_format($invoice->amount, 2) }}
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
                                {{ $invoice->created_at->format(setting('date_format', 'Y-m-d') . ' ' . setting('time_format', 'H:i')) }}
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
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Edit Invoice Details</h3>

                    <form wire:submit.prevent="updateInvoice" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Settlement Status *" />
                                <select wire:model="editStatus" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                    <option value="unpaid">Unpaid / Open</option>
                                    <option value="issued">Issued / Pending</option>
                                    <option value="paid">Paid &amp; Settled</option>
                                </select>
                                <x-input-error :messages="$errors->get('editStatus')" class="mt-1" />
                            </div>
                        </div>

                        @if(!$isSalesOrderInvoice)
                            <!-- Edit Invoice Item Lines -->
                            <div class="border-t border-gray-150 pt-4 space-y-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-bold text-gray-900">Line Items</h4>
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="addEditBlankLine" class="px-2.5 py-1 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-lg text-[10px] font-bold">+ Add Blank Row</button>
                                        <button type="button" wire:click="addEditItemLine" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[10px] font-bold">+ Add Product</button>
                                    </div>
                                </div>

                                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                                    @foreach($editItems as $idx => $item)
                                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end bg-slate-50/50 p-3 rounded-2xl border border-slate-100 relative group">
                                            @if(count($editItems) > 1)
                                                <button type="button" wire:click="removeEditItemLine({{ $idx }})" class="absolute -top-2 -right-2 bg-rose-100 text-rose-600 hover:bg-rose-500 hover:text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold transition-colors shadow-sm opacity-0 group-hover:opacity-100 z-10">&times;</button>
                                            @endif
                                            
                                            <div class="col-span-2">
                                                @if($item['is_blank'])
                                                    <label class="block text-[10px] text-gray-400 font-bold uppercase">Custom Description</label>
                                                    <input type="text" wire:model.live="editItems.{{ $idx }}.description" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" placeholder="e.g. Consulting Fees">
                                                    <x-input-error :messages="$errors->get('editItems.'.$idx.'.description')" class="mt-1" />
                                                @else
                                                    <label class="block text-[10px] text-gray-400 font-bold uppercase">Product Item</label>
                                                    <select wire:model.live="editItems.{{ $idx }}.product_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                                        <option value="">-- Choose Product --</option>
                                                        @foreach($products as $p)
                                                            <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('editItems.'.$idx.'.product_id')" class="mt-1" />
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Quantity</label>
                                                <input type="number" step="0.01" wire:model.live="editItems.{{ $idx }}.quantity" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Unit Price</label>
                                                <input type="number" step="0.01" wire:model.live="editItems.{{ $idx }}.unit_price" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                            </div>
                                            <div class="text-right">
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Line Total</label>
                                                <div class="mt-2 font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0), 2) }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Financials / GST -->
                            <div class="border-t border-gray-150 pt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4 border-r border-gray-150 pr-4">
                                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-150">
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Tax Type</label>
                                            <select wire:model.live="editGstType" class="mt-1 block w-full rounded-lg border-gray-300 text-xs text-gray-700 font-semibold">
                                                <option value="exclusive">Exclusive</option>
                                                <option value="inclusive">Inclusive</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Rate (%)</label>
                                            <input type="number" step="0.01" wire:model.live="editGstPercentage" class="mt-1 block w-full rounded-lg border-gray-300 text-xs text-gray-700">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] text-gray-400 font-bold uppercase">Flat Shipping / Handling ($)</label>
                                        <input type="number" step="0.01" wire:model.live="editShippingAmount" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                    </div>
                                </div>

                                <div class="flex flex-col justify-end space-y-3">
                                    @php
                                        $editTotals = $this->calculateTotals($editItems, $editGstPercentage > 0, $editGstType, $editGstPercentage, $editShippingAmount ?: 0);
                                    @endphp
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Subtotal:</span>
                                        <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($editTotals['subtotal'], 2) }}</span>
                                    </div>
                                    
                                    @if($editGstPercentage > 0)
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">GST ({{ $editGstPercentage }}% {{ ucfirst($editGstType) }}):</span>
                                        <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($editTotals['tax'], 2) }}</span>
                                    </div>
                                    @endif
                                    
                                    @if($editShippingAmount > 0)
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Shipping & Handling:</span>
                                        <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($editShippingAmount ?: 0, 2) }}</span>
                                    </div>
                                    @endif

                                    <div class="flex justify-between items-center pt-2 border-t border-slate-200">
                                        <span class="text-slate-900 font-black uppercase tracking-wider text-xs">Grand Total:</span>
                                        <span class="font-mono font-black text-indigo-700 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($editTotals['total'], 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="border-t border-gray-150 pt-4">
                                <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex items-start gap-3">
                                    <svg class="w-5 h-5 text-indigo-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <div>
                                        <h4 class="text-sm font-bold text-indigo-900">Line Items Locked</h4>
                                        <p class="text-xs text-indigo-700 mt-1">This invoice is linked to a Sales Order. Line items and amounts must be modified from the original Sales Order.</p>
                                    </div>
                                </div>
                                <div class="flex justify-end mt-4">
                                    <div class="text-right">
                                        <div class="text-[10px] font-bold uppercase text-slate-400 mb-1">Total Invoice Amount</div>
                                        <div class="text-2xl font-black text-slate-800 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($editAmount, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif


                        <div>
                            <x-input-label value="Description / Notes" />
                            <textarea wire:model="editDescription" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Optional details..."></textarea>
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

    <!-- Create Manual Invoice Modal -->
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showCreateModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Create Manual Invoice</h3>

                    <form wire:submit.prevent="createManualInvoice" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Customer *" />
                            <select wire:model="newCustomerId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                <option value="">-- Choose Customer --</option>
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('newCustomerId')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Issue Date *" />
                                <input type="date" wire:model="newIssueDate" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700" required>
                                <x-input-error :messages="$errors->get('newIssueDate')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Due Date" />
                                <input type="date" wire:model="newDueDate" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700">
                            </div>
                        </div>

                        <!-- Invoice Item Lines -->
                        <div class="border-t border-gray-150 pt-4 space-y-3">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-gray-900">Line Items</h4>
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="addBlankLine" class="px-2.5 py-1 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-lg text-[10px] font-bold">+ Add Blank Row</button>
                                    <button type="button" wire:click="addItemLine" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[10px] font-bold">+ Add Product</button>
                                </div>
                            </div>

                            <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                                @foreach($newItems as $idx => $item)
                                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end bg-slate-50/50 p-3 rounded-2xl border border-slate-100 relative group">
                                        @if(count($newItems) > 1)
                                            <button type="button" wire:click="removeItemLine({{ $idx }})" class="absolute -top-2 -right-2 bg-rose-100 text-rose-600 hover:bg-rose-500 hover:text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold transition-colors shadow-sm opacity-0 group-hover:opacity-100 z-10">&times;</button>
                                        @endif
                                        
                                        <div class="col-span-2">
                                            @if($item['is_blank'])
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Custom Description</label>
                                                <input type="text" wire:model.live="newItems.{{ $idx }}.description" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" placeholder="e.g. Consulting Fees">
                                                <x-input-error :messages="$errors->get('newItems.'.$idx.'.description')" class="mt-1" />
                                            @else
                                                <label class="block text-[10px] text-gray-400 font-bold uppercase">Product Item</label>
                                                <select wire:model.live="newItems.{{ $idx }}.product_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                                    <option value="">-- Choose Product --</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('newItems.'.$idx.'.product_id')" class="mt-1" />
                                            @endif
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Quantity</label>
                                            <input type="number" step="0.01" wire:model.live="newItems.{{ $idx }}.quantity" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Unit Price</label>
                                            <input type="number" step="0.01" wire:model.live="newItems.{{ $idx }}.unit_price" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                        </div>
                                        <div class="text-right">
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Line Total</label>
                                            <div class="mt-2 font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0), 2) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Financials / GST -->
                        <div class="border-t border-gray-150 pt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4 border-r border-gray-150 pr-4">
                                <div>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="newApplyGst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="text-xs font-bold text-gray-700">Apply GST Tax to Order</span>
                                    </label>
                                </div>
                                
                                @if($newApplyGst)
                                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-150">
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Tax Type</label>
                                            <select wire:model.live="newGstType" class="mt-1 block w-full rounded-lg border-gray-300 text-xs text-gray-700 font-semibold">
                                                <option value="exclusive">Exclusive</option>
                                                <option value="inclusive">Inclusive</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 font-bold uppercase">Rate (%)</label>
                                            <input type="number" step="0.01" wire:model.live="newGstPercentage" class="mt-1 block w-full rounded-lg border-gray-300 text-xs text-gray-700">
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-[10px] text-gray-400 font-bold uppercase">Flat Shipping / Handling ($)</label>
                                    <input type="number" step="0.01" wire:model.live="newShippingAmount" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                </div>
                            </div>

                            <div class="flex flex-col justify-end space-y-3">
                                @php
                                    $totals = $this->calculateTotals($newItems, $newApplyGst, $newGstType, $newGstPercentage, $newShippingAmount ?: 0);
                                @endphp
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Subtotal:</span>
                                    <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($totals['subtotal'], 2) }}</span>
                                </div>
                                
                                @if($newApplyGst)
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">GST ({{ $newGstPercentage }}% {{ ucfirst($newGstType) }}):</span>
                                    <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($totals['tax'], 2) }}</span>
                                </div>
                                @endif
                                
                                @if($newShippingAmount > 0)
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Shipping & Handling:</span>
                                    <span class="font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($newShippingAmount ?: 0, 2) }}</span>
                                </div>
                                @endif

                                <div class="flex justify-between items-center pt-2 border-t border-slate-200">
                                    <span class="text-slate-900 font-black uppercase tracking-wider text-xs">Grand Total:</span>
                                    <span class="font-mono font-black text-indigo-700 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($totals['total'], 2) }}</span>
                                </div>
                            </div>
                        </div>


                        <div>
                            <x-input-label value="Description / Notes" />
                            <textarea wire:model="newDescription" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Optional details..."></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Create Invoice</button>
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
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
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
                            <a href="{{ route('pdf.invoice', $selectedInvoice->id) }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1.5" target="_blank">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Download PDF
                            </a>
                            <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                            <button wire:click="$set('showInvoiceModal', false)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    @if($selectedInvoice)
                        <!-- PRINT READY AREA (A4 Standard corporate letterhead layout) -->
                        <div id="printable-invoice-area" class="bg-white p-2 rounded-2xl select-text print-content-wrapper">
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
                                        {!! nl2br(e(setting('company_location', 'Corporate Logistics, Warehousing & Supply Chain Operations Hub.\nJebel Ali Free Zone, Dubai, United Arab Emirates'))) !!}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <h2 class="text-3xl font-black text-indigo-600 font-mono uppercase tracking-tight">INVOICE</h2>
                                    <div class="text-xs text-slate-500 font-mono font-bold mt-1">INV-#{{ $selectedInvoice->id }}</div>
                                    <div class="text-[10px] text-slate-400 font-medium font-mono mt-0.5">Date: {{ $selectedInvoice->created_at->format(setting('date_format', 'Y-m-d')) }}</div>
                                </div>
                            </div>

                            <!-- Billing Info -->
                            <div class="grid grid-cols-2 gap-8 mb-8 text-xs font-semibold">
                                <div class="space-y-1">
                                    <h4 class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Client Recipient</h4>
                                    @if($selectedInvoice->salesOrder && $selectedInvoice->salesOrder->customer)
                                        <p class="text-sm font-black text-slate-850">{{ $selectedInvoice->salesOrder->customer->name }}</p>
                                        @if($selectedInvoice->salesOrder->customer->company_name)
                                            <p class="text-indigo-600 font-bold">{{ $selectedInvoice->salesOrder->customer->company_name }}</p>
                                        @endif
                                        <p class="text-slate-500 leading-relaxed font-normal">
                                            Email: {{ $selectedInvoice->salesOrder->customer->email ?? 'N/A' }}<br>
                                            Phone: {{ $selectedInvoice->salesOrder->customer->phone ?? 'N/A' }}
                                        </p>
                                    @elseif($selectedInvoice->customer)
                                        <!-- FALLBACK FOR MANUAL INVOICE -->
                                        @if($selectedInvoice->customer)
                                            <p class="text-sm font-black text-slate-850">{{ $selectedInvoice->customer->name }}</p>
                                            @if($selectedInvoice->customer->company_name)
                                                <p class="text-indigo-600 font-bold">{{ $selectedInvoice->customer->company_name }}</p>
                                            @endif
                                        @else
                                            <p class="text-sm font-black text-slate-850">Unknown Customer</p>
                                        @endif
                                        <p class="text-slate-500 leading-relaxed font-normal">
                                            Email: {{ $selectedInvoice->customer->email ?? 'N/A' }}<br>
                                            Phone: {{ $selectedInvoice->customer->phone ?? 'N/A' }}
                                        </p>
                                    @else
                                        <p class="text-sm font-black text-slate-850">N/A</p>
                                    @endif
                                </div>
                                <div class="text-right space-y-1">
                                    <h4 class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Operational Tracking</h4>
                                    @if($selectedInvoice->sales_order_id)
                                        <p class="text-slate-700 font-bold">Sales Order: <span class="font-mono text-slate-900 font-extrabold">SO-#{{ $selectedInvoice->sales_order_id }}</span></p>
                                    @else
                                        <p class="text-slate-700 font-bold">Type: <span class="font-mono text-slate-900 font-extrabold">Manual Invoice</span></p>
                                    @endif
                                    <p class="text-slate-700 font-bold">Status: 
                                        <span class="font-black uppercase {{ $selectedInvoice->status === 'paid' ? 'text-emerald-600' : 'text-amber-600' }}">
                                            {{ $selectedInvoice->status }}
                                        </span>
                                    </p>

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
                                                        @if($item->product)
                                                            <div class="font-black text-slate-800 text-xs">{{ $item->product->name }}</div>
                                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">SKU: {{ $item->product->sku }}</div>
                                                        @else
                                                            <div class="font-black text-slate-800 text-xs">{{ $item->description ?? 'Custom Line Item' }}</div>
                                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">Custom Line</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-bold text-slate-650">
                                                        {{ number_format($item->quantity, 0) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-mono text-slate-650">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-black font-mono text-slate-800">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($item->total_price, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            @forelse($selectedInvoice->items as $item)
                                                <tr>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        @if($item->product)
                                                            <div class="font-black text-slate-800 text-xs">{{ $item->product->name }}</div>
                                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">SKU: {{ $item->product->sku }}</div>
                                                        @else
                                                            <div class="font-black text-slate-800 text-xs">{{ $item->description ?? 'Custom Line Item' }}</div>
                                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">Custom Line</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-bold text-slate-650">
                                                        {{ number_format($item->quantity, 0) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-mono text-slate-650">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-black font-mono text-slate-800">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($item->total_price, 2) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="px-4 py-4 whitespace-normal">
                                                        <div class="font-black text-slate-800 text-xs">Manual Invoice Billing</div>
                                                        <div class="text-xs text-slate-500 mt-1">{{ $selectedInvoice->description ?? 'No specific line items attached.' }}</div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-bold text-slate-650">1</td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-mono text-slate-650">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($selectedInvoice->amount, 2) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-black font-mono text-slate-800">
                                                        {{ setting('currency_symbol', '$') }}{{ number_format($selectedInvoice->amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Grand total sheet -->
                            <div class="flex justify-between items-start border-t border-slate-150 pt-6">
                                <div class="max-w-md">
                                    <h4 class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider">Payment Verification Terms</h4>
                                    <p class="text-[9.5px] text-slate-400 mt-1 max-w-sm leading-relaxed font-normal">
                                        This document represents a legally verified commercial billing invoice generated automatically by the {{ setting('website_name', 'SCM ERP System') }} general ledger. All shipments are subject to standard warehouse audits and delivery dispatch sheets.
                                    </p>
                                </div>
                                <div class="text-right w-72">
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1">
                                        <span>Subtotal:</span>
                                        <span class="font-mono text-slate-700">{{ setting('currency_symbol', '$') }}{{ number_format(($selectedInvoice->amount - $selectedInvoice->tax_amount - ($selectedInvoice->shipping_amount ?? 0)), 2) }}</span>
                                    </div>
                                    @if($selectedInvoice->tax_amount > 0)
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1">
                                        <span>GST ({{ $selectedInvoice->gst_percentage ?? 0 }}% {{ ucfirst($selectedInvoice->gst_type ?? 'exclusive') }}):</span>
                                        <span class="font-mono text-slate-700">{{ setting('currency_symbol', '$') }}{{ number_format($selectedInvoice->tax_amount ?? 0, 2) }}</span>
                                    </div>
                                    @endif
                                    @if($selectedInvoice->shipping_amount > 0)
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1 border-b border-slate-100">
                                        <span>Shipping & Handling:</span>
                                        <span class="font-mono text-slate-700">{{ setting('currency_symbol', '$') }}{{ number_format($selectedInvoice->shipping_amount ?? 0, 2) }}</span>
                                    </div>
                                    @else
                                    <div class="border-b border-slate-100 mb-1"></div>
                                    @endif
                                    <div class="flex justify-between text-base font-black text-slate-800 pt-3">
                                        <span class="text-slate-500 font-normal">Total Balance:</span>
                                        <span class="font-mono text-indigo-600 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($selectedInvoice->amount, 2) }}</span>
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
                                    <span class="text-[8.5px] block mr-4">{{ setting('website_name', 'SCM ERP System') }} Operations Core</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

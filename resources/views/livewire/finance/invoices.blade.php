<?php

use function Livewire\Volt\{state, mount, updated};
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;

state([
    'invoices' => [],
    'customers' => [],
    'products' => [],
    
    // View Modal State (read-only letterhead)
    'showInvoiceModal' => false,
    'selectedInvoice' => null,

    // Unified Inline Workspace State (like PO/SO)
    'isEditing' => false,
    'invoiceId' => null,
    
    // Unified Form fields
    'customer_id' => '',
    'amount' => 0,
    'tax_amount' => 0,
    'shipping_amount' => 0,
    'issue_date' => '',
    'due_date' => '',
    'description' => '',
    'gst_type' => 'exclusive',
    'gst_percentage' => 0,
    'apply_gst' => false,
    'items' => [],
    'status' => 'issued',
    'isSalesOrderInvoice' => false,
    'showForm' => false,
]);

mount(function () {
    if (!auth()->user()->can('view receivables')) abort(403);
    $this->gst_percentage = (float)setting('default_gst_percentage', '18');
    $this->gst_type = setting('default_gst_type', 'exclusive');
    $this->loadData();
    $this->resetForm();
});

$loadData = function () {
    $this->invoices = Invoice::with(['salesOrder.customer', 'salesOrder.items.product', 'customer', 'items.product'])
        ->latest()
        ->get();
    $this->customers = App\Models\Customer::orderBy('name')->get();
    $this->products = Product::orderBy('name')->get();
};

$resetForm = function () {
    $this->amount = 0;
    $this->tax_amount = 0;
    $this->shipping_amount = 0;
    $this->customer_id = '';
    $this->issue_date = now()->toDateString();
    $this->due_date = now()->addDays(30)->toDateString();
    $this->description = '';
    $this->apply_gst = false;
    $this->gst_type = setting('default_gst_type', 'exclusive');
    $this->gst_percentage = (float)setting('default_gst_percentage', '18');
    $this->status = 'issued';
    $this->items = [
        ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
    ];
    $this->isEditing = false;
    $this->invoiceId = null;
    $this->isSalesOrderInvoice = false;
    $this->showForm = false;
};

$toggleForm = function () {
    if ($this->showForm && !$this->isEditing) {
        $this->showForm = false;
    } else {
        $this->resetForm();
        $this->showForm = true;
    }
};

// Item line methods
$addItemLine = function () {
    $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false];
};
$addBlankLine = function () {
    $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true];
};
$removeItemLine = function ($index) {
    if (count($this->items) > 1) {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }
    $this->recalculateTotals();
};

updated(['items.*.product_id'], function ($value, $key) {
    $parts = explode('.', $key);
    $index = $parts[1] ?? null;
    
    if ($index !== null && isset($this->items[$index])) {
        $productId = $value;
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $this->items[$index]['unit_price'] = $product->unit_price;
            }
        }
    }
    $this->recalculateTotals();
});

updated(['apply_gst', 'gst_type', 'gst_percentage', 'shipping_amount', 'items.*.quantity', 'items.*.unit_price'], function () {
    $this->recalculateTotals();
});

// Calculations
$recalculateTotals = function () {
    if ($this->isSalesOrderInvoice) {
        return;
    }
    $subtotal = 0;
    foreach($this->items as $item) {
        $subtotal += ((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0));
    }
    
    $this->tax_amount = 0;
    if ($this->apply_gst) {
        $gstPct = (float)$this->gst_percentage;
        if ($this->gst_type === 'inclusive') {
            $this->tax_amount = $subtotal - ($subtotal / (1 + ($gstPct / 100)));
        } else {
            $this->tax_amount = $subtotal * ($gstPct / 100);
        }
    }
    
    $this->amount = $subtotal + (float)$this->shipping_amount;
    if ($this->apply_gst && $this->gst_type === 'exclusive') {
        $this->amount += $this->tax_amount;
    }
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
    $this->dispatch('toast', type: 'success', message: 'Invoice status toggled successfully.');
};

$deleteInvoice = function ($id) {
    $invoice = Invoice::findOrFail($id);
    $invoice->delete();
    $this->loadData();
    $this->dispatch('toast', type: 'success', message: 'Invoice deleted successfully.');
};

$editInvoice = function ($id) {
    $invoice = Invoice::with('items')->findOrFail($id);
    $this->invoiceId = $invoice->id;
    $this->amount = $invoice->amount;
    $this->status = $invoice->status;
    $this->customer_id = $invoice->customer_id;
    $this->issue_date = $invoice->issue_date;
    $this->due_date = $invoice->due_date;
    $this->tax_amount = $invoice->tax_amount;
    $this->shipping_amount = $invoice->shipping_amount;
    $this->description = $invoice->description;
    $this->gst_type = $invoice->gst_type ?? 'exclusive';
    $this->gst_percentage = $invoice->gst_percentage ?? 0;
    $this->apply_gst = $invoice->tax_amount > 0;
    $this->isSalesOrderInvoice = $invoice->sales_order_id !== null;
    
    $this->items = [];
    if (!$this->isSalesOrderInvoice) {
        foreach ($invoice->items as $item) {
            $this->items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'is_blank' => $item->product_id === null,
            ];
        }
        
        // Handle legacy manual invoices without items
        if (empty($this->items)) {
            if ($invoice->amount > 0) {
                $subtotal = $invoice->amount - $invoice->tax_amount - $invoice->shipping_amount;
                $this->items[] = [
                    'product_id' => '',
                    'description' => $invoice->description ?: 'Legacy Manual Invoice Amount',
                    'quantity' => 1,
                    'unit_price' => max(0, $subtotal),
                    'is_blank' => true,
                ];
            } else {
                $this->items[] = [
                    'product_id' => '',
                    'description' => '',
                    'quantity' => 1,
                    'unit_price' => 0.00,
                    'is_blank' => false
                ];
            }
        }
    }
    
    $this->recalculateTotals();
    $this->isEditing = true;
    $this->showForm = true;
    $this->dispatch('toast', type: 'success', message: 'Details loaded successfully.');
};

$saveInvoice = function () {
    if ($this->isEditing) {
        $this->validate([
            'status' => 'required|string|in:issued,paid,unpaid',
        ]);

        $invoice = Invoice::findOrFail($this->invoiceId);
        
        if (!$this->isSalesOrderInvoice) {
            $this->validate([
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);
            
            foreach ($this->items as $idx => $item) {
                if (empty($item['is_blank']) && empty($item['product_id'])) {
                    $this->addError("items.{$idx}.product_id", "Product is required.");
                }
                if (!empty($item['is_blank']) && empty($item['description'])) {
                    $this->addError("items.{$idx}.description", "Description is required for custom lines.");
                }
            }
            if ($this->getErrorBag()->isNotEmpty()) return;

            $this->recalculateTotals();
            
            $invoice->update([
                'amount' => $this->amount,
                'tax_amount' => $this->tax_amount,
                'shipping_amount' => $this->shipping_amount ?: 0,
                'status' => $this->status,
                'description' => $this->description,
                'gst_type' => $this->gst_type,
                'gst_percentage' => $this->gst_percentage,
            ]);
            
            $invoice->items()->delete();
            foreach ($this->items as $item) {
                $invoice->items()->create([
                    'product_id' => empty($item['is_blank']) ? $item['product_id'] : null,
                    'description' => $item['is_blank'] ? $item['description'] : null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }
        } else {
            $invoice->update([
                'status' => $this->status,
                'description' => $this->description,
            ]);
        }

        $this->resetForm();
        $this->loadData();
        $this->dispatch('toast', type: 'success', message: 'Invoice updated successfully.');
    } else {
        $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);
        
        foreach ($this->items as $idx => $item) {
            if (empty($item['is_blank']) && empty($item['product_id'])) {
                $this->addError("items.{$idx}.product_id", "Product is required.");
            }
            if (!empty($item['is_blank']) && empty($item['description'])) {
                $this->addError("items.{$idx}.description", "Description is required for custom lines.");
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) return;
        
        $this->recalculateTotals();

        $invoice = Invoice::create([
            'customer_id' => $this->customer_id,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'shipping_amount' => $this->shipping_amount ?: 0,
            'gst_type' => $this->apply_gst ? $this->gst_type : 'exclusive',
            'gst_percentage' => $this->apply_gst ? $this->gst_percentage : 0,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date ?: null,
            'description' => $this->description,
            'status' => 'issued',
        ]);
        
        foreach ($this->items as $item) {
            $invoice->items()->create([
                'product_id' => empty($item['is_blank']) ? $item['product_id'] : null,
                'description' => $item['is_blank'] ? $item['description'] : null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        $this->resetForm();
        $this->loadData();
        $this->dispatch('toast', type: 'success', message: 'Manual invoice created successfully.');
    }
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
        
        <div class="flex items-center gap-3">
            @can('create invoices')
                <button type="button" wire:click="toggleForm" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors shadow-indigo-500/20">
                    {{ $showForm ? 'Close Form' : '+ New Invoice' }}
                </button>
            @endcan
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

    <!-- Inline Creator and Editor Desk Workspace -->
    @if($showForm)
    <div>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">
                    {{ $isEditing ? 'Edit Invoice' : 'Create Invoice' }}
                </h3>
                <p class="text-xs text-slate-550">
                    {{ $isEditing ? 'Modify fields inside the invoice document layout below.' : 'Fill in the fields on the invoice document layout below.' }}
                </p>
            </div>
            <button type="button" wire:click="resetForm" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200 rounded-xl transition-colors" title="Close Form">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @php
            $selectedCust = $customers->firstWhere('id', $customer_id);
            $subtotalValue = 0;
            if ($isSalesOrderInvoice && $invoiceId) {
                $invoiceModel = $invoices->firstWhere('id', $invoiceId);
                if ($invoiceModel) {
                    $selectedCust = $invoiceModel->salesOrder ? $invoiceModel->salesOrder->customer : $invoiceModel->customer;
                    $subtotalValue = $invoiceModel->amount - $invoiceModel->tax_amount - $invoiceModel->shipping_amount;
                }
            } else {
                foreach($items as $item) {
                    $subtotalValue += ((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0));
                }
            }
        @endphp
        <form wire:submit.prevent="saveInvoice">
            <div class="bg-white shadow-2xl border border-slate-200 p-12 relative overflow-hidden text-xs text-slate-750 font-semibold" style="min-height: 1000px; font-family: 'Inter', system-ui, sans-serif;">
                <!-- Top branding accent border -->
                <div class="absolute top-0 left-0 right-0 h-2 bg-indigo-600"></div>

                <!-- Document Header -->
                <div class="flex justify-between items-start border-b-2 border-indigo-600 pb-6 mb-8 mt-2">
                    <div>
                        @if(setting('website_logo'))
                            <img src="{{ setting('website_logo') }}" alt="Logo" class="max-h-12 mb-2 object-contain">
                        @else
                            <h1 class="text-xl font-black text-slate-800 tracking-tight">{{ setting('website_name', 'SCM ERP System') }}</h1>
                        @endif
                        <div class="text-[10px] text-slate-550 whitespace-pre-line mt-1 font-semibold leading-relaxed">
                            {!! nl2br(e(setting('company_location', "Corporate Logistics, Warehousing & Supply Chain Operations Hub.\nJebel Ali Free Zone, Dubai, United Arab Emirates"))) !!}
                        </div>
                    </div>
                    <div class="text-right">
                        <h2 class="text-3xl font-black text-indigo-600 font-mono uppercase tracking-tight">Invoice</h2>
                        <div class="text-xs text-slate-500 font-mono font-bold mt-1">
                            {{ $isEditing ? 'INV-#' . $invoiceId : 'INV-DRAFT' }}
                        </div>
                        <div class="text-[10px] text-slate-500 font-mono font-bold mt-2 space-y-1">
                            @if($isEditing)
                                <div>
                                    <span class="text-slate-400">Status:</span>
                                    <select wire:model.live="status" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] font-extrabold uppercase text-indigo-600 p-0 rounded inline-block cursor-pointer" required>
                                        <option value="unpaid">Unpaid / Open</option>
                                        <option value="issued">Issued / Pending</option>
                                        <option value="paid">Paid &amp; Settled</option>
                                    </select>
                                </div>
                            @endif
                            <div>
                                <span class="text-slate-400">Issue Date:</span>
                                @if($isSalesOrderInvoice)
                                    <span class="font-bold text-slate-700">{{ $issue_date }}</span>
                                @else
                                    <input type="date" wire:model.live="issue_date" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] font-bold font-mono p-0.5 w-28 ml-1 rounded" required>
                                    <x-input-error :messages="$errors->get('issue_date')" class="mt-0.5 text-right text-[10px]" />
                                @endif
                            </div>
                            <div>
                                <span class="text-slate-400">Due Date:</span>
                                @if($isSalesOrderInvoice)
                                    <span class="font-bold text-slate-700">{{ $due_date ?: '--' }}</span>
                                @else
                                    <input type="date" wire:model.live="due_date" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] font-bold font-mono p-0.5 w-28 ml-1 rounded">
                                    <x-input-error :messages="$errors->get('due_date')" class="mt-0.5 text-right text-[10px]" />
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Panels -->
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Client Recipient *</div>
                        <div class="text-xs text-slate-700 leading-relaxed font-medium">
                            @if($isEditing)
                                <strong class="text-slate-900 text-sm font-bold block mb-1">{{ $selectedCust->name ?? 'N/A' }}</strong>
                                @if($selectedCust && $selectedCust->company_name)
                                    <p class="text-indigo-600 font-bold mb-1">{{ $selectedCust->company_name }}</p>
                                @endif
                                <span class="text-slate-555">Email:</span> {{ $selectedCust->email ?? 'N/A' }}<br>
                                <span class="text-slate-555">Phone:</span> {{ $selectedCust->phone ?? 'N/A' }}
                            @else
                                <select wire:model.live="customer_id" class="w-full border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs p-1 text-slate-800 font-bold mb-2 rounded cursor-pointer" required>
                                    <option value="">-- Choose Customer --</option>
                                    @foreach($customers as $cust)
                                        <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />

                                @if($selectedCust)
                                    @if($selectedCust->company_name)
                                        <p class="text-indigo-600 font-bold mb-1">{{ $selectedCust->company_name }}</p>
                                    @endif
                                    <span class="text-slate-500">Email:</span> {{ $selectedCust->email ?? 'N/A' }}<br>
                                    <span class="text-slate-500">Phone:</span> {{ $selectedCust->phone ?? 'N/A' }}
                                @else
                                    <span class="text-slate-400 italic">Please select a customer to populate recipient details.</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Shipping Details</div>
                        <div class="text-xs text-slate-600 leading-relaxed font-medium">
                            @if($selectedCust && $selectedCust->shipping_address)
                                <strong>Delivery Address:</strong><br>
                                <span class="block text-slate-600 mt-1 whitespace-pre-line">{{ $selectedCust->shipping_address }}</span>
                            @else
                                <strong>Delivery Address:</strong><br>
                                As per standard SCM terms.
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-8">
                    @if($isSalesOrderInvoice)
                        <!-- SO Invoice Items are Locked, Render read-only standard design -->
                        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex items-start gap-3 mb-4">
                            <svg class="w-5 h-5 text-indigo-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <div>
                                <h4 class="text-sm font-bold text-indigo-900">Line Items Locked</h4>
                                <p class="text-xs text-indigo-755 mt-1">This invoice is linked to a Sales Order. Line items and amounts must be modified from the original Sales Order.</p>
                            </div>
                        </div>
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                    <th class="p-3 text-left w-12 rounded-l-xl">#</th>
                                    <th class="p-3 text-left">Item Description</th>
                                    <th class="p-3 text-center w-20">Quantity</th>
                                    <th class="p-3 text-right w-24">Unit Price</th>
                                    <th class="p-3 text-right w-24">Subtotal</th>
                                    <th class="p-3 text-right w-24 rounded-r-xl">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @if($isEditing && $invoiceId)
                                    @php
                                        $invoiceModel = $invoices->firstWhere('id', $invoiceId);
                                    @endphp
                                    @if($invoiceModel && $invoiceModel->salesOrder)
                                        @php
                                            $totalSub = 0;
                                            foreach($invoiceModel->salesOrder->items as $itm) {
                                                $totalSub += ((float)$itm->quantity * (float)$itm->unit_price);
                                            }
                                            $tax = (float)($invoiceModel->gst_amount ?? ($invoiceModel->tax_amount ?? 0));
                                        @endphp
                                        @foreach($invoiceModel->salesOrder->items as $idx => $item)
                                            @php
                                                $lineSub = (float)$item->quantity * (float)$item->unit_price;
                                                $taxShare = $totalSub > 0 ? ($lineSub / $totalSub) * $tax : 0;
                                                $lineTot = $lineSub + $taxShare;
                                            @endphp
                                            <tr class="{{ $idx % 2 === 1 ? 'bg-slate-50/50' : '' }} font-medium">
                                                <td class="p-3 text-slate-400">{{ $idx + 1 }}</td>
                                                <td class="p-3">
                                                    <strong class="text-slate-800 font-bold block">{{ $item->product ? $item->product->name : ($item->description ?? 'Custom Line Item') }}</strong>
                                                    @if($item->product && $item->product->sku)
                                                        <span class="text-[10px] text-slate-400 font-mono">SKU: {{ $item->product->sku }}</span>
                                                    @endif
                                                </td>
                                                <td class="p-3 text-center text-slate-700 font-mono font-bold">{{ $item->quantity }}</td>
                                                <td class="p-3 text-right text-slate-600 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}</td>
                                                <td class="p-3 text-right text-slate-600 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($lineSub, 2) }}</td>
                                                <td class="p-3 text-right text-slate-800 font-mono font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($lineTot, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endif
                            </tbody>
                        </table>
                    @else
                        <!-- Manual Invoice Items are fully editable inline -->
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                    <th class="p-3 text-left w-12 rounded-l-xl">#</th>
                                    <th class="p-3 text-left">Item Description / Product selection</th>
                                    <th class="p-3 text-center w-20">Quantity</th>
                                    <th class="p-3 text-right w-24">Unit Price</th>
                                    <th class="p-3 text-right w-24">Subtotal</th>
                                    <th class="p-3 text-right w-24">Total</th>
                                    <th class="p-3 text-center w-12 rounded-r-xl"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @php $lineIndex = 1; @endphp
                                @foreach($items as $idx => $item)
                                    @php
                                        $lineSub = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                                        $totalSub = (float)$subtotalValue;
                                        $lineTax = $totalSub > 0 ? ($lineSub / $totalSub) * (float)($gst_amount ?? 0) : 0;
                                        $lineTot = $lineSub + $lineTax;
                                    @endphp
                                    <tr class="font-medium hover:bg-slate-50/30 transition-colors">
                                        <td class="p-3 text-slate-400">{{ $lineIndex++ }}</td>
                                        <td class="p-3">
                                            @if($item['is_blank'])
                                                <input type="text" wire:model.live="items.{{ $idx }}.description" class="w-full border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-bold p-1 text-slate-800 rounded" placeholder="Enter custom description..." required>
                                                <x-input-error :messages="$errors->get('items.'.$idx.'.description')" class="mt-1" />
                                            @else
                                                <select wire:model.live="items.{{ $idx }}.product_id" class="w-full border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-bold p-1 text-slate-705 rounded" required>
                                                    <option value="">-- Choose Product --</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('items.'.$idx.'.product_id')" class="mt-1" />
                                            @endif
                                        </td>
                                        <td class="p-3 text-center">
                                            <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.quantity" class="w-16 text-center border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono font-bold p-1 text-slate-800 rounded" required>
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex items-center justify-end">
                                                <span class="text-slate-400 font-mono mr-1">{{ setting('currency_symbol', '$') }}</span>
                                                <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.unit_price" class="w-20 text-right border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono p-1 text-slate-800 rounded" required>
                                            </div>
                                        </td>
                                        <td class="p-3 text-right text-slate-600 font-mono">
                                            {{ setting('currency_symbol', '$') }}{{ number_format($lineSub, 2) }}
                                        </td>
                                        <td class="p-3 text-right text-slate-800 font-mono font-bold">
                                            {{ setting('currency_symbol', '$') }}{{ number_format($lineTot, 2) }}
                                        </td>
                                        <td class="p-3 text-center">
                                            @if(count($items) > 1)
                                                <button type="button" wire:click="removeItemLine({{ $idx }})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Add lines controller directly under the table -->
                        <div class="flex items-center gap-3 mt-4 justify-start">
                            <button type="button" wire:click="addBlankLine" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-750 text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1">
                                + Add Blank Row
                            </button>
                            <button type="button" wire:click="addItemLine" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-100 transition-colors flex items-center gap-1">
                                + Add Product Line
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Notes & Totals -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start border-t border-slate-150 pt-6">
                    <div class="md:col-span-7 bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Remarks / Notes</div>
                            <textarea wire:model.live.debounce.150ms="description" rows="3" class="w-full border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-semibold p-1.5 rounded" placeholder="Optional details..."></textarea>
                        </div>
                        @if(!$isSalesOrderInvoice)
                            <div class="border-t border-slate-200 pt-3 flex flex-wrap items-center gap-4">
                                <label class="inline-flex items-center gap-2 cursor-pointer font-bold">
                                    <input type="checkbox" wire:model.live="apply_gst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                    <span class="text-xs font-bold text-slate-705">Apply GST Tax</span>
                                </label>
                                
                                @if($apply_gst)
                                    <div class="inline-flex items-center gap-3 p-1.5 bg-white rounded-xl border border-slate-150">
                                        <div>
                                            <span class="text-[9px] font-bold text-slate-400 uppercase mr-1">Type:</span>
                                            <select wire:model.live="gst_type" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] p-0 font-bold rounded cursor-pointer">
                                                <option value="exclusive">Exclusive</option>
                                                <option value="inclusive">Inclusive</option>
                                            </select>
                                        </div>
                                        <div class="border-l border-slate-200 pl-2">
                                            <span class="text-[9px] font-bold text-slate-400 uppercase mr-1">Rate:</span>
                                            <input type="number" step="0.01" wire:model.live="gst_percentage" class="w-10 border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-[11px] text-right p-0 font-mono font-bold rounded">%
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="md:col-span-5">
                        <table class="w-full text-xs font-semibold text-slate-600">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @if($isSalesOrderInvoice && $invoiceId)
                                    @php
                                        $invoiceModel = $invoices->firstWhere('id', $invoiceId);
                                    @endphp
                                    @if($invoiceModel)
                                        <tr>
                                            <td class="py-2.5 text-left text-slate-400">Subtotal:</td>
                                            <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format(($invoiceModel->amount - $invoiceModel->tax_amount - ($invoiceModel->shipping_amount ?? 0)), 2) }}</td>
                                        </tr>
                                        @if($invoiceModel->tax_amount > 0)
                                        <tr>
                                            <td class="py-2.5 text-left text-slate-400">GST ({{ $invoiceModel->gst_percentage ?? 0 }}% {{ ucfirst($invoiceModel->gst_type ?? 'exclusive') }}):</td>
                                            <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($invoiceModel->tax_amount, 2) }}</td>
                                        </tr>
                                        @endif
                                        @if($invoiceModel->shipping_amount > 0)
                                        <tr>
                                            <td class="py-2.5 text-left text-slate-400">Shipping & Handling:</td>
                                            <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($invoiceModel->shipping_amount, 2) }}</td>
                                        </tr>
                                        @endif
                                        <tr class="border-t-2 border-slate-200">
                                            <td class="py-3 text-left font-black text-slate-900 text-sm">Grand Total:</td>
                                            <td class="py-3 text-right font-mono font-black text-indigo-600 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($invoiceModel->amount, 2) }}</td>
                                        </tr>
                                    @endif
                                @else
                                    <tr>
                                        <td class="py-2.5 text-left text-slate-400">Subtotal:</td>
                                        <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($subtotalValue, 2) }}</td>
                                    </tr>
                                    @if($apply_gst && $tax_amount > 0)
                                    <tr>
                                        <td class="py-2.5 text-left text-slate-400">GST ({{ $gst_percentage }}% {{ ucfirst($gst_type) }}):</td>
                                        <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($tax_amount, 2) }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <td class="py-2.5 text-left text-slate-400">Shipping &amp; Handling:</td>
                                        <td class="py-2.5 text-right">
                                            <div class="flex items-center justify-end">
                                                <span class="text-slate-400 font-mono mr-1">{{ setting('currency_symbol', '$') }}</span>
                                                <input type="number" step="0.01" wire:model.live="shipping_amount" class="w-20 text-right border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono font-bold p-0.5 rounded">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="border-t-2 border-slate-200">
                                        <td class="py-3 text-left font-black text-slate-900 text-sm">Grand Total:</td>
                                        <td class="py-3 text-right font-mono font-black text-indigo-600 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($amount, 2) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Signature and hash -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 border-t border-slate-200 pt-8 mt-12 text-[10px] font-mono text-slate-400">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <span class="font-bold text-slate-500 uppercase block text-[8px]">ERP Verification Token</span>
                            <span class="text-[8px] font-mono tracking-tight">{{ hash('sha256', ($invoiceId ?: 'DRAFT') . '-' . $amount . '-' . now()->toDateString()) }}</span>
                        </div>
                    </div>
                    <div class="text-right flex flex-col justify-end">
                        <div class="border-b border-slate-200 w-48 ml-auto mb-1"></div>
                        <span class="font-bold text-slate-500 uppercase block">Financial Ledger Officer</span>
                        <span class="text-[8.5px] block">{{ setting('website_name', 'SCM ERP System') }} Operations Core</span>
                    </div>
                </div>

                <!-- Page Footer -->
                <div class="absolute bottom-6 left-12 right-12 flex justify-between items-center text-[10px] text-slate-400 font-medium">
                    <span>{{ setting('website_name', 'SCM ERP System') }} &copy; {{ date('Y') }}. All rights reserved.</span>
                    <span>Page 1 of 1</span>
                </div>
            </div>

            <!-- Premium High-Visibility Action Toolbar -->
            <div class="mt-8 p-4 bg-slate-50/90 backdrop-blur rounded-2xl border border-slate-200/80 shadow-lg flex justify-end gap-3 no-print">
                <button type="button" wire:click="resetForm" class="px-6 py-3 bg-white hover:bg-slate-50 text-slate-705 text-xs font-black uppercase tracking-wider rounded-xl border border-slate-300 shadow-sm transition-all active:scale-[0.98]">
                    Cancel &amp; Close
                </button>
                <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md hover:shadow-indigo-500/20 transition-all active:scale-[0.98] cursor-pointer">
                    {{ $isEditing ? 'Save & Update Invoice' : 'Approve & Create Invoice' }}
                </button>
            </div>
        </form>
    </div>
    @endif



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
                                    <a href="{{ route('pdf.invoice', $invoice->id) }}" target="_blank" class="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors inline-flex items-center" title="PDF Preview & Download">
                                        <!-- PDF/File Icon -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                    <button wire:click="editInvoice({{ $invoice->id }})" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-lg transition-colors inline-flex items-center" title="Edit invoice details">
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
</div>

<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public PurchaseOrder $order;
    public $items;
    public $products;
    public $currencySymbol = '$';

    // Form fields for adding items
    public $product_id = '';
    public $quantity = 1;
    public $unit_price = 0;

    public function mount(PurchaseOrder $order)
    {
        $this->order = $order->load('supplier.products', 'items.product', 'grns');
        $this->items = $this->order->items;
        $this->products = Product::all();
        $this->currencySymbol = \App\Models\Currency::where('code', $this->order->currency_code)->value('symbol') ?? setting('currency_symbol', '$');
    }

    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            if ($product) {
                // If attached to supplier, use pivot price. Otherwise cost_price.
                $supplierProduct = $this->order->supplier->products->where('id', $product->id)->first();
                if ($supplierProduct) {
                    $this->unit_price = $supplierProduct->pivot->price;
                } else {
                    $this->unit_price = $product->cost_price;
                }
            }
        }
    }

    public function addItem()
    {
        if (!auth()->user()->can('edit purchase_orders') && !auth()->user()->hasRole('Super Admin')) abort(403);
        $this->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $this->order->items()->create([
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
        ]);

        // Auto attach to supplier if not already attached
        $isAttached = $this->order->supplier->products()->where('product_id', $this->product_id)->exists();
        if (!$isAttached) {
            $this->order->supplier->products()->attach($this->product_id, [
                'price' => $this->unit_price
            ]);
        }

        $this->updateTotal();
        $this->resetItemForm();
        $this->mount($this->order); // Refresh data
        $this->dispatch('toast', type: 'success', message:  'Item added to PO.');
    }

    public function removeItem($itemId)
    {
        if (!auth()->user()->can('edit purchase_orders') && !auth()->user()->hasRole('Super Admin')) abort(403);
        $this->order->items()->where('id', $itemId)->delete();
        $this->updateTotal();
        $this->mount($this->order);
        $this->dispatch('toast', type: 'success', message:  'Item removed from PO.');
    }

    public function updateTotal()
    {
        $subtotal = $this->order->items()->sum(\DB::raw('quantity * unit_price'));
        
        $gstPct = $this->order->gst_percentage;
        $gstAmt = 0;
        $total = 0;

        if ($this->order->gst_type === 'inclusive') {
            // Price already includes GST. 
            // Total = Subtotal (which is the sum of (quantity * unit_price))
            $total = $subtotal;
            // Formula: GST Amount = Total - (Total / (1 + (GST% / 100)))
            $gstAmt = $total - ($total / (1 + ($gstPct / 100)));
        } else {
            // Exclusive: GST is added on top of the subtotal
            $gstAmt = $subtotal * ($gstPct / 100);
            $total = $subtotal + $gstAmt;
        }

        $approvalStatus = 'approved';
        if ($total > 10000) {
            $approvalStatus = 'pending_approval';
        }

        $this->order->update([
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmt,
            'total_amount' => $total,
            'approval_status' => $approvalStatus,
        ]);
    }

    public function approveOrder()
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('approve purchase_orders')) abort(403);
        $this->order->update(['approval_status' => 'approved', 'approval_notes' => 'Approved by Director']);
        $this->mount($this->order);
        $this->dispatch('toast', type: 'success', message: 'PO Approved successfully.');
    }

    public function rejectOrder()
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('approve purchase_orders')) abort(403);
        $this->order->update(['approval_status' => 'rejected', 'approval_notes' => 'Rejected by Director', 'status' => 'cancelled']);
        $this->mount($this->order);
        $this->dispatch('toast', type: 'error', message: 'PO Rejected.');
    }

    public function resetItemForm()
    {
        $this->product_id = '';
        $this->quantity = 1;
        $this->unit_price = 0;
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <!-- Back Navigation & Top Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-850 font-black transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                &larr; Back to Purchase Orders
            </a>
        </div>
        <div class="flex items-center flex-wrap gap-3">
            <span class="px-3 py-1 text-xs font-black uppercase rounded-full {{ $order->status === 'received' ? 'bg-green-55 text-green-800 border border-green-200' : ($order->status === 'cancelled' ? 'bg-red-55 text-red-800 border border-red-200' : 'bg-indigo-50 text-indigo-800 border border-indigo-200') }}">
                Status: {{ $order->status }}
            </span>
            @if($order->approval_status === 'pending_approval')
                <span class="px-3 py-1 text-xs font-black uppercase rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                    Pending Director Approval
                </span>
            @elseif($order->approval_status === 'rejected')
                <span class="px-3 py-1 text-xs font-black uppercase rounded-full bg-rose-50 text-rose-800 border border-rose-200">
                    Approval Rejected
                </span>
            @endif

            <a href="{{ route('pdf.purchaseOrder', $order->id) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-colors gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download PDF
            </a>

            @if($order->approval_status === 'approved' && $order->status === 'approved' && $order->total_amount > 0 && $order->items->count() > 0)
                <a href="{{ route('grn.index') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-colors gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Receive Goods (GRN)
                </a>
            @elseif($order->approval_status === 'pending_approval' && (auth()->user()->hasRole('Super Admin') || auth()->user()->can('approve purchase_orders')))
                <div class="flex items-center gap-2">
                    <button wire:click="rejectOrder" class="px-4 py-2 bg-rose-100 hover:bg-rose-250 text-rose-700 text-xs font-bold rounded-xl transition-colors">Reject Order</button>
                    <button wire:click="approveOrder" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-colors">Approve Order</button>
                </div>
            @endif
        </div>
    </div>

    <!-- Main Workspace (Desktop Split, Mobile Stacked) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- Document Sheet Preview (Left 2 Cols) -->
        <div class="lg:col-span-2 ">
            <div class="bg-white shadow-2xl border border-slate-200 p-12 relative overflow-hidden" style="min-height: 1080px; font-family: 'Inter', system-ui, sans-serif;">
                <!-- Top Primary Branding Border Accent -->
                <div class="absolute top-0 left-0 right-0 h-2 bg-indigo-600"></div>

                <!-- Document Header -->
                <div class="flex justify-between items-start border-b-2 border-indigo-600 pb-6 mb-8 mt-2">
                    <div>
                        @if(setting('website_logo'))
                            <img src="{{ setting('website_logo') }}" alt="Logo" class="max-h-12 mb-2 object-contain">
                        @else
                            <h1 class="text-xl font-black text-slate-800 tracking-tight">{{ setting('website_name', 'SCM ERP System') }}</h1>
                        @endif
                        <div class="text-[10px] text-slate-500 whitespace-pre-line mt-1 font-semibold leading-relaxed">
                            {!! nl2br(e(setting('company_location', "123 Enterprise Way\nBusiness City, ST 12345"))) !!}
                        </div>
                    </div>
                    <div class="text-right">
                        <h1 class="text-3xl font-black text-indigo-600 uppercase tracking-wider mb-2">Purchase Order</h1>
                        <div class="text-xs text-slate-500 leading-relaxed font-semibold">
                            <strong>PO #:</strong> {{ setting('purchase_order_prefix', 'PO-') }}{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}<br>
                            <strong>Date:</strong> {{ $order->created_at->format('M d, Y') }}<br>
                            <strong>Status:</strong> <span class="uppercase font-extrabold" style="color: {{ $order->status === 'received' || $order->status === 'approved' ? '#10b981' : '#4f46e5' }}">{{ $order->status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info Boxes -->
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Vendor / Supplier</div>
                        <div class="text-xs text-slate-700 leading-relaxed font-medium">
                            <strong class="text-slate-900 text-sm font-bold block mb-1">{{ $order->supplier->name }}</strong>
                            @if($order->supplier->contact_person) <span class="text-slate-500">Attn:</span> {{ $order->supplier->contact_person }}<br> @endif
                            @if($order->supplier->address) <span class="block text-slate-600 my-1 whitespace-pre-line">{{ $order->supplier->address }}</span> @endif
                            @if($order->supplier->email) <span class="text-slate-500">Email:</span> {{ $order->supplier->email }}<br> @endif
                            @if($order->supplier->phone) <span class="text-slate-500">Phone:</span> {{ $order->supplier->phone }} @endif
                        </div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Shipping Details</div>
                        <div class="text-xs text-slate-600 leading-relaxed font-medium">
                            <strong>Delivery Address:</strong><br>
                            {!! nl2br(e(setting('company_location', 'As per standard terms.'))) !!}
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <table class="w-full border-collapse mb-8 text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <th class="p-3 text-left w-12 rounded-l-xl">#</th>
                            <th class="p-3 text-left">Item Description</th>
                            <th class="p-3 text-center w-20">Quantity</th>
                            <th class="p-3 text-right w-24">Unit Price</th>
                            <th class="p-3 text-right w-24">Subtotal</th>
                            <th class="p-3 text-right w-24 font-bold">Total</th>
                            <th class="p-3 text-right w-20 rounded-r-xl no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $index => $item)
                            @php
                                $lineSub = (float)$item->quantity * (float)$item->unit_price;
                                $totalSub = (float)($order->subtotal ?? 0);
                                $lineGst = $totalSub > 0 ? ($lineSub / $totalSub) * (float)($order->gst_amount ?? 0) : 0;
                                $lineTot = $lineSub + $lineGst;
                            @endphp
                            <tr class="{{ $index % 2 === 1 ? 'bg-slate-50/50' : '' }} hover:bg-slate-50 transition-colors group font-medium">
                                <td class="p-3 text-slate-400">{{ $index + 1 }}</td>
                                <td class="p-3">
                                    <strong class="text-slate-800 font-bold block">{{ $item->product ? $item->product->name : ($item->description ?? 'Custom Line Item') }}</strong>
                                    @if($item->product && $item->product->sku)
                                        <span class="text-[10px] text-slate-400 font-mono">SKU: {{ $item->product->sku }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-center text-slate-700 font-mono font-bold">{{ $item->quantity }}</td>
                                <td class="p-3 text-right text-slate-600 font-mono">{{ $currencySymbol }}{{ number_format($item->unit_price, 2) }}</td>
                                <td class="p-3 text-right text-slate-605 font-mono">{{ $currencySymbol }}{{ number_format($lineSub, 2) }}</td>
                                <td class="p-3 text-right text-slate-800 font-mono font-bold">{{ $currencySymbol }}{{ number_format($lineTot, 2) }}</td>
                                <td class="p-3 text-right no-print">
                                    <button wire:click="removeItem({{ $item->id }})" class="text-rose-600 hover:text-rose-800 font-bold opacity-0 group-hover:opacity-100 transition-opacity">Remove</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400 italic font-semibold">No items in this purchase order. Add items using the control panel on the right.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Notes & Totals -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start mb-12">
                    <div class="md:col-span-7 bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Remarks / Special Instructions</div>
                        <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line font-medium">
                            {{ $order->remarks ?: 'Thank you for your business. Please process payment according to the terms listed.' }}
                        </p>
                    </div>
                    <div class="md:col-span-5">
                        <table class="w-full text-xs font-semibold text-slate-600">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">Subtotal:</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ $currencySymbol }}{{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                @if($order->gst_amount > 0)
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">GST ({{ $order->gst_percentage }}% {{ ucfirst($order->gst_type) }}):</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ $currencySymbol }}{{ number_format($order->gst_amount, 2) }}</td>
                                </tr>
                                @endif
                                <tr class="border-t-2 border-slate-200">
                                    <td class="py-3 text-left font-black text-slate-900 text-sm">Total Due:</td>
                                    <td class="py-3 text-right font-mono font-black text-indigo-600 text-lg">{{ $currencySymbol }}{{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="grid grid-cols-3 gap-8 mt-12 pt-8 border-t border-slate-200">
                    <div class="text-center">
                        <div class="h-8 border-b border-slate-300 mx-4 mb-2"></div>
                        <div class="text-[10px] font-bold text-slate-700 uppercase">Prepared By</div>
                        <div class="text-[9px] text-slate-400">Authorized Representative</div>
                    </div>
                    <div class="text-center">
                        <div class="h-8 border-b border-slate-300 mx-4 mb-2"></div>
                        <div class="text-[10px] font-bold text-slate-700 uppercase">Approved By</div>
                        <div class="text-[9px] text-slate-400">
                            @if($order->approval_status === 'approved')
                                Director / Management
                            @else
                                Department Head
                            @endif
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="h-8 border-b border-slate-300 mx-4 mb-2"></div>
                        <div class="text-[10px] font-bold text-slate-700 uppercase">Accepted By</div>
                        <div class="text-[9px] text-slate-400">Client / Vendor Signature</div>
                    </div>
                </div>

                <!-- Page Footer -->
                <div class="absolute bottom-6 left-12 right-12 flex justify-between items-center text-[10px] text-slate-400">
                    <span>{{ setting('website_name', 'SCM ERP System') }} &copy; {{ date('Y') }}. All rights reserved.</span>
                    <span>Page 1 of 1</span>
                </div>
            </div>
        </div>

        <!-- Add Items Control Panel (Right 1 Col) -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-150 sticky top-6">
            <h3 class="text-lg font-black text-slate-800 tracking-tight mb-4">Control Panel</h3>
            <div class="border-t border-slate-100 pt-4 mb-6">
                <h4 class="text-xs font-black text-slate-450 uppercase tracking-wider mb-3">Add Item to PO</h4>
                <form wire:submit="addItem" class="space-y-4">
                    <div>
                        <x-input-label for="product_id" value="Product" class="text-xs text-slate-500 font-bold" />
                        <select wire:model.live="product_id" id="product_id" class="mt-1 block w-full text-xs font-bold text-slate-700 border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm" required>
                            <option value="">Select Product...</option>
                            @if($order->supplier->products->count() > 0)
                                <optgroup label="Attached to {{ $order->supplier->name }}">
                                    @foreach($order->supplier->products as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }} (Agreed Price: {{ $currencySymbol }}{{ $p->pivot->price }})</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            <optgroup label="Other Catalog Products (Will Auto-Attach)">
                                @foreach($products->diff($order->supplier->products) as $p)
                                    <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <x-input-error :messages="$errors->get('product_id')" class="mt-1 text-xs text-red-500" />
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="quantity" value="Quantity" class="text-xs text-slate-500 font-bold" />
                            <x-text-input wire:model="quantity" id="quantity" type="number" min="1" class="mt-1 block w-full text-xs font-bold rounded-xl border-slate-300 shadow-sm" required />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-1 text-xs text-red-500" />
                        </div>
                        <div>
                            <x-input-label for="unit_price" value="Unit Price ({{ $order->currency_code }})" class="text-xs text-slate-500 font-bold" />
                            <x-text-input wire:model="unit_price" id="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs font-bold rounded-xl border-slate-300 shadow-sm" required />
                            <x-input-error :messages="$errors->get('unit_price')" class="mt-1 text-xs text-red-500" />
                        </div>
                    </div>

                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-colors">
                        Add Item Line
                    </button>
                </form>
            </div>

            @if($order->terms_and_conditions)
            <div class="border-t border-slate-100 pt-4">
                <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">Terms &amp; Conditions</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed font-semibold whitespace-pre-line bg-slate-50 p-4 rounded-xl border border-slate-150">{{ $order->terms_and_conditions }}</p>
            </div>
            @endif
        </div>

    </div>
</div>


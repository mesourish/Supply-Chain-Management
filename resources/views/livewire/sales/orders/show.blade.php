<?php

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public SalesOrder $order;
    public $items;
    public $products;

    // Form fields for adding items
    public $product_id = '';
    public $quantity = 1;
    public $unit_price = 0;

    public $apply_gst = false;
    public $gst_type = 'exclusive';
    public $gst_percentage = 0;
    public $shipping_amount = 0;

    public function mount(SalesOrder $order)
    {
        $this->order = $order->load('customer', 'items.product', 'invoices', 'shipments');
        $this->items = $this->order->items;
        $this->products = Product::all();
        
        $this->gst_percentage = (float)setting('default_gst_percentage', '18');
        $this->apply_gst = $this->order->tax_amount > 0;
        $this->shipping_amount = (float)$this->order->shipping_amount;
        $this->gst_type = setting('default_gst_type', 'exclusive');
    }

    public function updatedApplyGst() { $this->updateTotal(); }
    public function updatedGstType() { $this->updateTotal(); }
    public function updatedShippingAmount() { $this->updateTotal(); }
    public function updatedGstPercentage() { $this->updateTotal(); }

    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            if ($product) {
                $this->unit_price = $product->unit_price; // Auto-fill selling price
            }
        }
    }

    public function addItem()
    {
        if (!auth()->user()->can('edit sales_orders')) abort(403);
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

        $this->updateTotal();
        $this->resetItemForm();
        $this->mount($this->order); // Refresh data
        $this->dispatch('toast', type: 'success', message:  'Item added to Sales Order.');
    }

    public function removeItem($itemId)
    {
        if (!auth()->user()->can('edit sales_orders')) abort(403);
        $this->order->items()->where('id', $itemId)->delete();
        $this->updateTotal();
        $this->mount($this->order);
        $this->dispatch('toast', type: 'success', message:  'Item removed from SO.');
    }

    public function updateTotal()
    {
        $subtotal = $this->order->items()->sum(\DB::raw('quantity * unit_price')) ?? 0;
        $tax = 0;

        if ($this->apply_gst) {
            if ($this->gst_type === 'inclusive') {
                $tax = $subtotal - ($subtotal / (1 + ($this->gst_percentage / 100)));
            } else {
                $tax = $subtotal * ($this->gst_percentage / 100);
            }
        }

        $total = $subtotal + (float)$this->shipping_amount;
        if ($this->apply_gst && $this->gst_type === 'exclusive') {
            $total += $tax;
        }

        $this->order->update([
            'total_amount' => $total,
            'tax_amount' => $tax,
            'shipping_amount' => (float)($this->shipping_amount ?: 0),
        ]);
        $this->order->refresh();
    }

    public function resetItemForm()
    {
        $this->product_id = '';
        $this->quantity = 1;
        $this->unit_price = 0;
    }

    public function generateInvoice()
    {
        if (!auth()->user()->can('edit sales_orders')) abort(403);
        if ($this->order->total_amount <= 0) {
            $this->dispatch('toast', type: 'success', message:  'Cannot generate invoice for empty order.');
            return;
        }
        
        $this->order->invoices()->create([
            'amount' => $this->order->total_amount,
            'tax_amount' => $this->order->tax_amount,
            'shipping_amount' => $this->order->shipping_amount,
            'gst_type' => $this->gst_type,
            'gst_percentage' => $this->apply_gst ? $this->gst_percentage : 0,
            'status' => 'unpaid',
        ]);
        
        $this->order->update(['status' => 'processing']);
        $this->mount($this->order);
        $this->dispatch('toast', type: 'success', message:  'Invoice Generated.');
    }
    
    public function fulfillShipment()
    {
        if (!auth()->user()->can('fulfill sales_orders')) abort(403);

        \DB::transaction(function () {
            $shipment = $this->order->shipments()->create([
                'status' => 'processing',
                'tracking_number' => 'TRK-' . strtoupper(uniqid()),
            ]);
            
            $this->order->update(['status' => 'shipped']);

            // Auto-generate OUT transactions
            foreach ($this->order->items as $item) {
                \App\Models\InventoryTransaction::create([
                    'product_id' => $item->product_id,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'SalesOrder',
                    'reference_id' => $this->order->id,
                    'notes' => 'SO Fulfilled (Shipment: ' . $shipment->tracking_number . ')',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        $this->mount($this->order);
        $this->dispatch('toast', type: 'success', message:  'Shipment Created, SO marked as shipped, and Inventory Updated!');
    }
}; ?>


<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <!-- Back Navigation & Top Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <a href="{{ route('sales-orders.index') }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-850 font-black transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                &larr; Back to Sales Orders
            </a>
        </div>
        <div class="flex items-center flex-wrap gap-3 font-semibold">
            <span class="px-3 py-1 text-xs font-black uppercase rounded-full {{ $order->status === 'shipped' || $order->status === 'delivered' ? 'bg-green-55 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
                Status: {{ $order->status }}
            </span>

            <a href="{{ route('pdf.salesOrder', $order->id) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-colors gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download PDF
            </a>

            @if($order->invoices->count() === 0)
                <button wire:click="generateInvoice" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1">
                    Generate Invoice
                </button>
            @endif
            @if($order->status === 'processing' && $order->shipments->count() === 0)
                <button wire:click="fulfillShipment" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1">
                    Create Shipment
                </button>
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
                        <h1 class="text-3xl font-black text-indigo-600 uppercase tracking-wider mb-2">Sales Order</h1>
                        <div class="text-xs text-slate-500 leading-relaxed font-semibold">
                            <strong>SO #:</strong> {{ setting('sales_order_prefix', 'SO-') }}{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}<br>
                            <strong>Date:</strong> {{ $order->created_at->format('M d, Y') }}<br>
                            <strong>Status:</strong> <span class="uppercase font-extrabold text-indigo-600">{{ $order->status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info Boxes -->
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Bill To Customer</div>
                        <div class="text-xs text-slate-700 leading-relaxed font-medium">
                            @if($order->customer)
                                <strong class="text-slate-900 text-sm font-bold block mb-1">{{ $order->customer->name }}</strong>
                                @if($order->customer->contact_person) <span class="text-slate-500">Attn:</span> {{ $order->customer->contact_person }}<br> @endif
                                @if($order->customer->billing_address) <span class="block text-slate-600 my-1 whitespace-pre-line">{{ $order->customer->billing_address }}</span> @endif
                                @if($order->customer->email) <span class="text-slate-500">Email:</span> {{ $order->customer->email }}<br> @endif
                                @if($order->customer->phone) <span class="text-slate-500">Phone:</span> {{ $order->customer->phone }} @endif
                            @else
                                <span class="text-slate-400 italic font-semibold">Unknown Customer</span>
                            @endif
                        </div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Shipping Details</div>
                        <div class="text-xs text-slate-600 leading-relaxed font-medium">
                            @if($order->customer && $order->customer->shipping_address)
                                <strong>Delivery Address:</strong><br>
                                <span class="block text-slate-600 mt-1 whitespace-pre-line">{{ $order->customer->shipping_address }}</span>
                            @else
                                <strong>Delivery Address:</strong><br>
                                As per standard terms.
                            @endif
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
                        @php
                            $totalSub = (float)($items->sum(fn($itm) => (float)$itm->quantity * (float)$itm->unit_price) ?: 1);
                            $taxAmt = (float)($order->tax_amount ?? 0);
                        @endphp
                        @forelse($items as $index => $item)
                            @php
                                $lineSub = (float)$item->quantity * (float)$item->unit_price;
                                $lineTax = ($lineSub / $totalSub) * $taxAmt;
                                $lineTot = $lineSub + $lineTax;
                            @endphp
                            <tr class="{{ $index % 2 === 1 ? 'bg-slate-50/50' : '' }} hover:bg-slate-50 transition-colors group font-medium">
                                <td class="p-3 text-slate-400">{{ $index + 1 }}</td>
                                <td class="p-3">
                                    <strong class="text-slate-800 font-bold block">
                                        @if($item->product)
                                            {{ $item->product->name }}
                                        @else
                                            {{ $item->description ?? 'Custom Line Item' }}
                                        @endif
                                    </strong>
                                    @if($item->product && $item->product->sku)
                                        <span class="text-[10px] text-slate-400 font-mono">SKU: {{ $item->product->sku }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-center text-slate-700 font-mono font-bold">{{ $item->quantity }}</td>
                                <td class="p-3 text-right text-slate-600 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}</td>
                                <td class="p-3 text-right text-slate-605 font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($lineSub, 2) }}</td>
                                <td class="p-3 text-right text-slate-800 font-mono font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($lineTot, 2) }}</td>
                                <td class="p-3 text-right no-print">
                                    <button wire:click="removeItem({{ $item->id }})" class="text-rose-600 hover:text-rose-800 font-bold opacity-0 group-hover:opacity-100 transition-opacity">Remove</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400 italic font-semibold">No items in this sales order. Add items using the control panel on the right.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Notes & Totals -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start mb-12">
                    <div class="md:col-span-7 bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Remarks / Special Instructions</div>
                        <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line font-semibold">
                            {{ $order->description ?: 'Thank you for your business. Please process payment according to the terms listed.' }}
                        </p>
                    </div>
                    <div class="md:col-span-5">
                        <table class="w-full text-xs font-semibold text-slate-600">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">Subtotal:</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format(($order->total_amount - ($apply_gst && $gst_type === 'exclusive' ? $order->tax_amount : 0) - $order->shipping_amount), 2) }}</td>
                                </tr>
                                @if($apply_gst && $order->tax_amount > 0)
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">GST ({{ $gst_percentage }}% {{ ucfirst($gst_type) }}):</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($order->tax_amount, 2) }}</td>
                                </tr>
                                @endif
                                @if($order->shipping_amount > 0)
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">Shipping & Handling:</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($order->shipping_amount, 2) }}</td>
                                </tr>
                                @endif
                                <tr class="border-t-2 border-slate-200">
                                    <td class="py-3 text-left font-black text-slate-900 text-sm">Total Due:</td>
                                    <td class="py-3 text-right font-mono font-black text-indigo-600 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</td>
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
                        <div class="text-[9px] text-slate-400">Department Head</div>
                    </div>
                    <div class="text-center">
                        <div class="h-8 border-b border-slate-300 mx-4 mb-2"></div>
                        <div class="text-[10px] font-bold text-slate-700 uppercase">Accepted By</div>
                        <div class="text-[9px] text-slate-400">Client Signature</div>
                    </div>
                </div>

                <!-- Page Footer -->
                <div class="absolute bottom-6 left-12 right-12 flex justify-between items-center text-[10px] text-slate-400 font-medium">
                    <span>{{ setting('website_name', 'SCM ERP System') }} &copy; {{ date('Y') }}. All rights reserved.</span>
                    <span>Page 1 of 1</span>
                </div>
            </div>
        </div>

        <!-- Add Items Control Panel (Right 1 Col) -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-150 sticky top-6 space-y-6">
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight mb-4">Control Panel</h3>
                
                <div class="border-t border-slate-100 pt-4 mb-4">
                    <h4 class="text-xs font-black text-slate-450 uppercase tracking-wider mb-3">Billing Settings</h4>
                    <div class="space-y-3 text-xs font-semibold text-slate-600">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer font-bold">
                                <input type="checkbox" wire:model.live="apply_gst" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                Apply GST ({{ $gst_percentage }}%)
                            </label>
                        </div>
                        @if($apply_gst)
                            <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-150">
                                <div>
                                    <label class="text-[9px] font-bold uppercase text-slate-400">Tax Type</label>
                                    <select wire:model.live="gst_type" class="mt-1 block w-full text-xs font-bold text-slate-700 border-slate-350 focus:border-indigo-500 rounded-lg py-1 px-2">
                                        <option value="exclusive">Exclusive</option>
                                        <option value="inclusive">Inclusive</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold uppercase text-slate-400">Rate (%)</label>
                                    <input type="number" step="0.01" wire:model.live="gst_percentage" class="mt-1 block w-full text-xs font-bold text-slate-700 border-slate-350 focus:border-indigo-500 rounded-lg py-1 px-2">
                                </div>
                            </div>
                        @endif
                        <div class="flex items-center justify-between pt-2 border-t border-slate-55">
                            <label class="font-bold">Shipping:</label>
                            <div class="flex items-center">
                                <span class="mr-1 text-slate-400">{{ setting('currency_symbol', '$') }}</span>
                                <input type="number" step="0.01" wire:model.live.debounce.500ms="shipping_amount" class="w-24 text-right text-xs font-bold text-slate-700 border-slate-300 rounded-xl py-1 px-2">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <h4 class="text-xs font-black text-slate-450 uppercase tracking-wider mb-3">Add Item to SO</h4>
                <form wire:submit="addItem" class="space-y-4">
                    <div>
                        <x-input-label for="product_id" value="Product" class="text-xs text-slate-500 font-bold" />
                        <select wire:model.live="product_id" id="product_id" class="mt-1 block w-full text-xs font-bold text-slate-700 border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm" required>
                            <option value="">Select Product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                            @endforeach
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
                            <x-input-label for="unit_price" value="Unit Price ($)" class="text-xs text-slate-500 font-bold" />
                            <x-text-input wire:model="unit_price" id="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs font-bold rounded-xl border-slate-300 shadow-sm" required />
                            <x-input-error :messages="$errors->get('unit_price')" class="mt-1 text-xs text-red-500" />
                        </div>
                    </div>

                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-colors">
                        Add Item Line
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>


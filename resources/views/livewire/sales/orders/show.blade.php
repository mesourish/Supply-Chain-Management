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
    <div class="mb-4">
        <a href="{{ route('sales-orders.index') }}" class="text-indigo-600 hover:underline">&larr; Back to Sales Orders</a>
    </div>

    

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-4">
                    @if(setting('website_logo'))
                        <img src="{{ setting('website_logo') }}" alt="Logo" class="h-16 object-contain">
                    @endif
                    <div>
                        <h2 class="text-3xl font-black text-gray-900 tracking-tight">{{ setting('website_name', 'SCM ERP') }}</h2>
                        <p class="text-sm text-gray-500 whitespace-pre-line mt-1">{{ setting('company_location', '') }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <h2 class="text-3xl font-black text-indigo-700 tracking-tight uppercase">Sales Order</h2>
                    <p class="text-lg font-semibold text-gray-700 mt-1">{{ setting('sales_order_prefix', 'SO-') }}{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</p>
                    <p class="text-sm text-gray-500 mt-1">Date: {{ $order->created_at->format(setting('date_format', 'Y-m-d')) }}</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8 border-t border-gray-100 pt-8">
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Customer Details</h4>
                    @if($order->customer)
                        <p class="text-lg font-bold text-gray-900">{{ $order->customer->name }}</p>
                        @if($order->customer->contact_person) <p class="text-gray-600 text-sm mt-1">Attn: {{ $order->customer->contact_person }}</p> @endif
                        @if($order->customer->billing_address) <p class="text-gray-600 text-sm mt-1 whitespace-pre-line">{{ $order->customer->billing_address }}</p> @endif
                        @if($order->customer->email) <p class="text-gray-600 text-sm mt-1">{{ $order->customer->email }}</p> @endif
                        @if($order->customer->phone) <p class="text-gray-600 text-sm mt-1">{{ $order->customer->phone }}</p> @endif
                    @else
                        <p class="text-gray-600 text-sm mt-1">Unknown Customer</p>
                    @endif
                </div>
                <div class="text-right flex flex-col justify-end items-end">
                    <div class="mb-4">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Order Status</span>
                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-bold rounded-full {{ $order->status === 'shipped' || $order->status === 'delivered' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="text-right flex flex-col justify-end items-end gap-1">
                <div class="w-64 space-y-1 mb-4 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal:</span>
                        <span>{{ setting('currency_symbol', '$') }}{{ number_format(($order->total_amount - ($apply_gst && $gst_type === 'exclusive' ? $order->tax_amount : 0) - $order->shipping_amount), 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-gray-500">
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" wire:model.live="apply_gst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            GST ({{ $gst_percentage }}%)
                        </label>
                        <span>{{ setting('currency_symbol', '$') }}{{ number_format($order->tax_amount, 2) }}</span>
                    </div>
                    @if($apply_gst)
                    <div class="flex justify-between items-center text-gray-500 mt-1">
                        <span class="text-xs">Tax Type:</span>
                        <select wire:model.live="gst_type" class="text-xs border-gray-300 rounded py-1 pl-2 pr-6">
                            <option value="exclusive">Exclusive</option>
                            <option value="inclusive">Inclusive</option>
                        </select>
                    </div>
                    @endif
                    <div class="flex justify-between items-center text-gray-500 mt-2">
                        <span>Shipping:</span>
                        <div class="flex items-center">
                            <span class="mr-1">{{ setting('currency_symbol', '$') }}</span>
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="shipping_amount" class="w-20 text-right text-sm border-gray-300 rounded py-1 px-2">
                        </div>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-gray-900 border-t border-gray-200 pt-2">Total: {{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</h3>
                <div class="mt-4 space-x-2">
                    <a href="{{ route('pdf.salesOrder', $order->id) }}" target="_blank" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold py-2 px-4 rounded text-sm inline-flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Download PDF
                    </a>
                    @if($order->invoices->count() === 0)
                        <button wire:click="generateInvoice" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Generate Invoice
                        </button>
                    @endif
                    @if($order->status === 'processing' && $order->shipments->count() === 0)
                        <button wire:click="fulfillShipment" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Create Shipment
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Add Item</h3>
            <form wire:submit="addItem">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <x-input-label for="product_id" value="Product" />
                        <select wire:model="product_id" id="product_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select Product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quantity" value="Quantity" />
                        <x-text-input wire:model="quantity" id="quantity" type="number" min="1" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="unit_price" value="Unit Price ($)" />
                        <x-text-input wire:model="unit_price" id="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('unit_price')" class="mt-2" />
                    </div>
                </div>
                <div class="mt-4">
                    <x-primary-button>Add Item</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Order Items</h3>
            @if($items->count() > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($items as $item)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($item->product)
                                        {{ $item->product->name }} ({{ $item->product->sku }})
                                    @else
                                        {{ $item->description ?? 'Custom Line Item' }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $item->quantity }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ setting('currency_symbol', '$') }}{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ setting('currency_symbol', '$') }}{{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button wire:click="removeItem({{ $item->id }})" class="text-red-600 hover:text-red-900">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-gray-500">No items in this sales order.</p>
            @endif
        </div>
    </div>
</div>

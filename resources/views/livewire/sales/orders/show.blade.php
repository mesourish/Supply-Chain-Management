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

    public function mount(SalesOrder $order)
    {
        $this->order = $order->load('customer', 'items.product', 'invoices', 'shipments');
        $this->items = $this->order->items;
        $this->products = Product::all();
    }

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
        session()->flash('message', 'Item added to Sales Order.');
    }

    public function removeItem($itemId)
    {
        if (!auth()->user()->can('edit sales_orders')) abort(403);
        $this->order->items()->where('id', $itemId)->delete();
        $this->updateTotal();
        $this->mount($this->order);
        session()->flash('message', 'Item removed from SO.');
    }

    public function updateTotal()
    {
        $total = $this->order->items()->sum(\DB::raw('quantity * unit_price'));
        $this->order->update(['total_amount' => $total]);
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
            session()->flash('message', 'Cannot generate invoice for empty order.');
            return;
        }
        
        $this->order->invoices()->create([
            'amount' => $this->order->total_amount,
            'status' => 'unpaid',
        ]);
        
        $this->order->update(['status' => 'processing']);
        $this->mount($this->order);
        session()->flash('message', 'Invoice Generated.');
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
        session()->flash('message', 'Shipment Created, SO marked as shipped, and Inventory Updated!');
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="mb-4">
        <a href="{{ route('sales-orders.index') }}" class="text-indigo-600 hover:underline">&larr; Back to Sales Orders</a>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">Sales Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</h2>
                <p class="text-gray-600 mt-1">Customer: <strong>
                    @if($order->customer)
                        <a href="{{ route('customers.show', $order->customer->id) }}" class="text-indigo-600 hover:underline">{{ $order->customer->name }}</a>
                    @else
                        Unknown
                    @endif
                </strong></p>
                <p class="text-gray-600">Status: 
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        {{ ucfirst($order->status) }}
                    </span>
                </p>
            </div>
            <div class="text-right">
                <h3 class="text-xl font-bold text-gray-900">Total: {{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</h3>
                <div class="mt-2 space-x-2">
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
                                <td class="px-6 py-4 whitespace-nowrap">{{ $item->product->name }} ({{ $item->product->sku }})</td>
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

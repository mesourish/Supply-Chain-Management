<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public PurchaseOrder $order;
    public $items;
    public $products;

    // Form fields for adding items
    public $product_id = '';
    public $quantity = 1;
    public $unit_price = 0;

    public function mount(PurchaseOrder $order)
    {
        $this->order = $order->load('supplier', 'items.product', 'grns');
        $this->items = $this->order->items;
        $this->products = Product::all();
    }

    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            if ($product) {
                $this->unit_price = $product->cost_price; // Auto-fill cost
            }
        }
    }

    public function addItem()
    {
        if (!auth()->user()->can('edit purchase orders')) abort(403);
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
        session()->flash('message', 'Item added to PO.');
    }

    public function removeItem($itemId)
    {
        if (!auth()->user()->can('edit purchase orders')) abort(403);
        $this->order->items()->where('id', $itemId)->delete();
        $this->updateTotal();
        $this->mount($this->order);
        session()->flash('message', 'Item removed from PO.');
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

    public function generateGrn()
    {
        if (!auth()->user()->can('receive purchase_orders')) abort(403);

        \DB::transaction(function () {
            $grn = $this->order->grns()->create([
                'user_id' => auth()->id(),
                'status' => 'received',
                'notes' => 'Auto-generated GRN upon receiving.',
            ]);
            $this->order->update(['status' => 'received']);

            foreach ($this->order->items as $item) {
                \App\Models\InventoryTransaction::create([
                    'product_id' => $item->product_id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reference_type' => 'PurchaseOrder',
                    'reference_id' => $this->order->id,
                    'notes' => 'PO Received (GRN: ' . $grn->id . ')',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        $this->mount($this->order);
        session()->flash('message', 'Goods Receipt Note created, PO marked as received, and Inventory Updated!');
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="mb-4">
        <a href="{{ route('purchase-orders.index') }}" class="text-indigo-600 hover:underline">&larr; Back to Purchase Orders</a>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">Purchase Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</h2>
                <p class="text-gray-600 mt-1">Supplier: <strong>{{ $order->supplier->name }}</strong></p>
                <p class="text-gray-600">Status: 
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                        {{ ucfirst($order->status) }}
                    </span>
                </p>
            </div>
            <div class="text-right">
                <h3 class="text-xl font-bold text-gray-900">Total: {{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</h3>
                @if($order->status !== 'received')
                    <button wire:click="generateGrn" class="mt-2 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Receive Goods (GRN)
                    </button>
                @endif
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
            </div>
            @else
                <p class="text-gray-500">No items in this purchase order.</p>
            @endif
        </div>
    </div>
</div>

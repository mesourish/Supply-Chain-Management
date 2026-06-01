<?php

use App\Models\SalesOrder;
use App\Models\Customer;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $customer_id, $status = 'pending', $total_amount = 0;
    public $isEditing = false;
    public $orderId = null;

    // Items and GST fields
    public $items = [];
    public $apply_gst = false;
    public $gst_type = 'exclusive';
    public $gst_percentage = 18;
    public $shipping_amount = 0;
    public $tax_amount = 0;
    public $subtotal = 0;

    public function rules()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'status' => 'required|string',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function mount()
    {
        $this->gst_percentage = (float)setting('default_gst_percentage', '18');
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->items = [
            ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00]
        ];
    }

    public function updatedItems($value, $key)
    {
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            $index = $parts[0];
            $field = $parts[1];
            
            if ($field === 'product_id' && $value) {
                $product = \App\Models\Product::find($value);
                if ($product) {
                    $this->items[$index]['unit_price'] = $product->unit_price;
                }
            }
        }
        $this->recalculateTotals();
    }

    public function updatedApplyGst() { $this->recalculateTotals(); }
    public function updatedGstType() { $this->recalculateTotals(); }
    public function updatedGstPercentage() { $this->recalculateTotals(); }
    public function updatedShippingAmount() { $this->recalculateTotals(); }

    public function addItemLine()
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00];
        $this->recalculateTotals();
    }

    public function removeItemLine($index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
        $this->recalculateTotals();
    }

    public function recalculateTotals()
    {
        $this->subtotal = 0;
        foreach ($this->items as $item) {
            $this->subtotal += ((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0));
        }

        $this->tax_amount = 0;
        if ($this->apply_gst) {
            $gstPct = (float)$this->gst_percentage;
            if ($this->gst_type === 'inclusive') {
                $this->tax_amount = $this->subtotal - ($this->subtotal / (1 + ($gstPct / 100)));
            } else {
                $this->tax_amount = $this->subtotal * ($gstPct / 100);
            }
        }

        $this->total_amount = $this->subtotal + (float)$this->shipping_amount;
        if ($this->apply_gst && $this->gst_type === 'exclusive') {
            $this->total_amount += $this->tax_amount;
        }
    }

    public function save()
    {
        $this->validate();

        $this->recalculateTotals();

        \DB::transaction(function () {
            $so = SalesOrder::updateOrCreate(
                ['id' => $this->orderId],
                [
                    'customer_id' => $this->customer_id,
                    'status' => $this->status,
                    'total_amount' => $this->total_amount,
                    'tax_amount' => $this->tax_amount,
                    'shipping_amount' => (float)($this->shipping_amount ?: 0),
                ]
            );

            // Save items
            $so->items()->delete();
            foreach ($this->items as $item) {
                $so->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }
        });

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message:  $this->orderId ? 'SO Updated Successfully.' : 'SO Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $order = SalesOrder::with('items')->findOrFail($id);
        $this->orderId = $id;
        $this->customer_id = $order->customer_id;
        $this->status = $order->status;
        $this->total_amount = $order->total_amount;
        $this->tax_amount = $order->tax_amount;
        $this->shipping_amount = $order->shipping_amount;
        $this->apply_gst = $order->tax_amount > 0;
        
        $this->items = [];
        foreach ($order->items as $item) {
            $this->items[] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ];
        }
        
        if (empty($this->items)) {
            $this->items = [
                ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00]
            ];
        }

        $this->recalculateTotals();
        $this->isEditing = true;
    }

    public function delete($id)
    {
        SalesOrder::find($id)->delete();
        $this->dispatch('toast', type: 'success', message:  'SO Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->customer_id = '';
        $this->status = 'pending';
        $this->total_amount = 0;
        $this->tax_amount = 0;
        $this->shipping_amount = 0;
        $this->apply_gst = false;
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->gst_percentage = (float)setting('default_gst_percentage', '18');
        $this->orderId = null;
        $this->isEditing = false;
        $this->items = [
            ['product_id' => '', 'quantity' => 1, 'unit_price' => 0.00]
        ];
        $this->recalculateTotals();
    }

    public function with()
    {
        return [
            'orders' => SalesOrder::with(['customer'])->latest()->paginate(10),
            'customers' => Customer::all(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Sales Order' : 'Create Sales Order' }}</h2>

            

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="customer_id" value="Customer" />
                        <select wire:model="customer_id" id="customer_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select Customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" value="Status" />
                        <select wire:model="status" id="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                </div>

                <!-- Multiple Products Selection Section -->
                <div class="mt-6 border-t border-gray-150 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-wider">Sales Order Items</h3>
                        <button type="button" wire:click="addItemLine" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg transition-colors border border-indigo-100">
                            + Add Product Row
                        </button>
                    </div>

                    <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                        @foreach($items as $index => $item)
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end bg-slate-50/50 p-4 rounded-xl border border-slate-100 relative group">
                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItemLine({{ $index }})" class="absolute -top-2 -right-2 bg-rose-100 text-rose-600 hover:bg-rose-500 hover:text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold transition-colors shadow-sm">
                                        &times;
                                    </button>
                                @endif
                                
                                <div class="md:col-span-2">
                                    <x-input-label value="Product" />
                                    <select wire:model.live="items.{{ $index }}.product_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                        <option value="">Select Product...</option>
                                        @foreach(\App\Models\Product::all() as $p)
                                            <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('items.'.$index.'.product_id')" class="mt-1" />
                                </div>
                                
                                <div>
                                    <x-input-label value="Quantity" />
                                    <x-text-input wire:model.live="items.{{ $index }}.quantity" type="number" min="1" class="mt-1 block w-full" required />
                                    <x-input-error :messages="$errors->get('items.'.$index.'.quantity')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Unit Price" />
                                    <x-text-input wire:model.live="items.{{ $index }}.unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                                    <x-input-error :messages="$errors->get('items.'.$index.'.unit_price')" class="mt-1" />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Tax and Shipping panel -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-100 pt-6">
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model.live="apply_gst" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                    <span class="text-xs font-bold text-gray-700">Apply GST Tax to Order</span>
                                </label>
                            </div>
                            
                            @if($apply_gst)
                            <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-150">
                                <div>
                                    <x-input-label value="GST Type" />
                                    <select wire:model.live="gst_type" class="mt-1 block w-full rounded-md border-gray-300 text-xs font-semibold">
                                        <option value="exclusive">Exclusive</option>
                                        <option value="inclusive">Inclusive</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label value="GST Rate (%)" />
                                    <input type="number" step="0.01" wire:model.live="gst_percentage" class="mt-1 block w-full rounded-md border-gray-300 text-xs font-semibold">
                                </div>
                            </div>
                            @endif

                            <div>
                                <x-input-label value="Shipping / Handling amount" />
                                <input type="number" step="0.01" wire:model.live="shipping_amount" class="mt-1 block w-full rounded-md border-gray-300 text-xs font-semibold">
                            </div>
                        </div>

                        <!-- Pricing Summary Block -->
                        <div class="flex flex-col justify-end space-y-1.5 bg-slate-50 p-4 rounded-xl border border-slate-100 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal:</span>
                                <span class="font-mono font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($apply_gst)
                            <div class="flex justify-between text-gray-600">
                                <span>GST ({{ $gst_percentage }}% {{ ucfirst($gst_type) }}):</span>
                                <span class="font-mono font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($tax_amount, 2) }}</span>
                            </div>
                            @endif
                            @if($shipping_amount > 0)
                            <div class="flex justify-between text-gray-600">
                                <span>Shipping &amp; Handling:</span>
                                <span class="font-mono font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($shipping_amount, 2) }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between text-gray-900 font-extrabold text-base border-t border-slate-200 pt-1 mt-1">
                                <span>Grand Total:</span>
                                <span class="font-mono font-black text-indigo-700">{{ setting('currency_symbol', '$') }}{{ number_format($total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update SO' : 'Save SO' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Sales Orders</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SO #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($orders as $order)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">SO-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->customer)
                                        <a href="{{ route('customers.show', $order->customer->id) }}" class="text-indigo-600 hover:underline">{{ $order->customer->name }}</a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('sales-orders.show', $order) }}" class="text-blue-600 hover:text-blue-900 mr-3">Manage Items</a>
                                    <button wire:click="edit({{ $order->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $order->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>

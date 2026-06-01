<?php

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SystemConstant;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $supplier_id, $status = 'draft';
    public $subtotal = 0, $gst_type = 'exclusive', $gst_percentage = 0, $gst_amount = 0, $total_amount = 0;
    public $remarks = '', $terms_and_conditions = '';
    public $currency_code = 'USD', $exchange_rate = 1.0;
    public $isEditing = false;
    public $orderId = null;

    public $items = [];

    public function rules()
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'status' => 'required|string',
            'gst_type' => 'required|in:inclusive,exclusive',
            'gst_percentage' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'remarks' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function mount()
    {
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->gst_percentage = setting('default_gst_percentage', 0);
        $this->currency_code = setting('default_currency_code', 'USD');
        $this->remarks = setting('default_po_remarks', '');
        $this->terms_and_conditions = setting('default_po_terms', '');
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
                    $price = $product->cost_price;
                    if ($this->supplier_id) {
                        $supplier = Supplier::find($this->supplier_id);
                        if ($supplier) {
                            $supplierProduct = $supplier->products->where('id', $product->id)->first();
                            if ($supplierProduct) {
                                $price = $supplierProduct->pivot->price;
                            }
                        }
                    }
                    $this->items[$index]['unit_price'] = $price;
                }
            }
        }
        $this->recalculateTotals();
    }

    public function updatedSupplierId()
    {
        foreach ($this->items as $index => $item) {
            if ($item['product_id']) {
                $product = \App\Models\Product::find($item['product_id']);
                if ($product) {
                    $price = $product->cost_price;
                    $supplier = Supplier::find($this->supplier_id);
                    if ($supplier) {
                        $supplierProduct = $supplier->products->where('id', $product->id)->first();
                        if ($supplierProduct) {
                            $price = $supplierProduct->pivot->price;
                        }
                    }
                    $this->items[$index]['unit_price'] = $price;
                }
            }
        }
        $this->recalculateTotals();
    }

    public function updatedGstType() { $this->recalculateTotals(); }
    public function updatedGstPercentage() { $this->recalculateTotals(); }

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

        $gstPct = (float)$this->gst_percentage;
        if ($this->gst_type === 'inclusive') {
            $this->total_amount = $this->subtotal;
            $this->gst_amount = $this->total_amount - ($this->total_amount / (1 + ($gstPct / 100)));
        } else {
            $this->gst_amount = $this->subtotal * ($gstPct / 100);
            $this->total_amount = $this->subtotal + $this->gst_amount;
        }
    }

    public function save()
    {
        $this->validate();

        $supplier = Supplier::findOrFail($this->supplier_id);
        if (!$supplier->contactPersons()->where('is_primary', true)->exists()) {
            $this->addError('supplier_id', 'This supplier must have a primary contact person before a Purchase Order can be created.');
            return;
        }

        $this->recalculateTotals();

        $approvalStatus = 'approved';
        if ($this->total_amount > 10000) {
            $approvalStatus = 'pending_approval';
        }

        \DB::transaction(function () use ($approvalStatus) {
            $po = PurchaseOrder::updateOrCreate(
                ['id' => $this->orderId],
                [
                    'supplier_id' => $this->supplier_id,
                    'status' => $this->status,
                    'gst_type' => $this->gst_type,
                    'gst_percentage' => $this->gst_percentage,
                    'gst_amount' => $this->gst_amount,
                    'subtotal' => $this->subtotal,
                    'total_amount' => $this->total_amount,
                    'currency_code' => $this->currency_code,
                    'exchange_rate' => \App\Models\Currency::where('code', $this->currency_code)->value('exchange_rate') ?? 1.0,
                    'remarks' => $this->remarks,
                    'terms_and_conditions' => $this->terms_and_conditions,
                    'approval_status' => $approvalStatus,
                ]
            );

            // Save items
            $po->items()->delete();
            foreach ($this->items as $item) {
                $po->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);

                // Auto attach to supplier if not already attached
                $isAttached = $po->supplier->products()->where('product_id', $item['product_id'])->exists();
                if (!$isAttached) {
                    $po->supplier->products()->attach($item['product_id'], [
                        'price' => $item['unit_price']
                    ]);
                }
            }
        });

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message:  $this->orderId ? 'PO Updated Successfully.' : 'PO Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $order = PurchaseOrder::with('items')->findOrFail($id);
        $this->orderId = $id;
        $this->supplier_id = $order->supplier_id;
        $this->status = $order->status;
        $this->gst_type = $order->gst_type;
        $this->gst_percentage = $order->gst_percentage;
        $this->currency_code = $order->currency_code ?? 'USD';
        $this->exchange_rate = $order->exchange_rate ?? 1.0;
        $this->remarks = $order->remarks;
        $this->terms_and_conditions = $order->terms_and_conditions;
        
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
        PurchaseOrder::find($id)->delete();
        $this->dispatch('toast', type: 'success', message:  'PO Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->supplier_id = '';
        $this->status = 'draft';
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->gst_percentage = setting('default_gst_percentage', 0);
        $this->currency_code = setting('default_currency_code', 'USD');
        $this->exchange_rate = 1.0;
        $this->remarks = setting('default_po_remarks', '');
        $this->terms_and_conditions = setting('default_po_terms', '');
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
            'orders' => PurchaseOrder::with(['supplier'])->latest()->paginate(10),
            'suppliers' => Supplier::where('is_active', true)->get(),
            'currencies' => \App\Models\Currency::all(),
            'gst_percentages' => SystemConstant::where('type', 'gst_percentage')->where('is_active', true)->orderBy('value')->get(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Purchase Order' : 'Create Purchase Order' }}</h2>

            

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="supplier_id" value="Supplier" />
                        <select wire:model="supplier_id" id="supplier_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select Supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="currency_code" value="Currency" />
                        <select wire:model="currency_code" id="currency_code" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('currency_code')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" value="Status" />
                        <select wire:model="status" id="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="shipped">Shipped</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gst_type" value="GST Type" />
                        <select wire:model="gst_type" id="gst_type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="exclusive">Exclusive</option>
                            <option value="inclusive">Inclusive</option>
                        </select>
                        <x-input-error :messages="$errors->get('gst_type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gst_percentage" value="GST Percentage (%)" />
                        <select wire:model="gst_percentage" id="gst_percentage" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="0">0%</option>
                            @foreach($gst_percentages as $gst)
                                <option value="{{ $gst->value }}">{{ $gst->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('gst_percentage')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <x-input-label for="remarks" value="Remarks" />
                        <textarea wire:model="remarks" id="remarks" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="terms_and_conditions" value="Terms & Conditions" />
                        <textarea wire:model="terms_and_conditions" id="terms_and_conditions" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('terms_and_conditions')" class="mt-2" />
                    </div>
                </div>

                <!-- Multiple Products Selection Section -->
                <div class="mt-6 border-t border-gray-150 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-wider">Purchase Order Items</h3>
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

                    <!-- Totals Display Area -->
                    <div class="mt-4 flex flex-col items-end space-y-1 bg-slate-50 p-4 rounded-xl border border-slate-100 text-sm">
                        @php
                            $selectedCurrencySymbol = \App\Models\Currency::where('code', $currency_code)->value('symbol') ?? '$';
                        @endphp
                        <div class="flex justify-between w-64 text-gray-600">
                            <span>Subtotal:</span>
                            <span class="font-mono font-bold">{{ $selectedCurrencySymbol }}{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between w-64 text-gray-600">
                            <span>GST ({{ $gst_percentage }}% {{ ucfirst($gst_type) }}):</span>
                            <span class="font-mono font-bold">{{ $selectedCurrencySymbol }}{{ number_format($gst_amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between w-64 text-gray-900 font-extrabold text-base border-t border-slate-200 pt-1 mt-1">
                            <span>Grand Total ({{ $currency_code }}):</span>
                            <span class="font-mono font-black text-indigo-700">{{ $selectedCurrencySymbol }}{{ number_format($total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update PO' : 'Save PO' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Purchase Orders</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($orders as $order)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">PO-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $order->supplier->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                    @if($order->approval_status === 'pending_approval')
                                        <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                            Pending
                                        </span>
                                    @elseif($order->approval_status === 'rejected')
                                        <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Rejected
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ \App\Models\Currency::where('code', $order->currency_code)->value('symbol') ?? setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('purchase-orders.show', $order) }}" class="text-blue-600 hover:text-blue-900 mr-3">Manage Items</a>
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

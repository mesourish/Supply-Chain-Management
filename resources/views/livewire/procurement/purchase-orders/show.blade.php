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
    <div class="mb-4">
        <a href="{{ route('purchase-orders.index') }}" class="text-indigo-600 hover:underline">&larr; Back to Purchase Orders</a>
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
                    <h2 class="text-3xl font-black text-indigo-700 tracking-tight uppercase">Purchase Order</h2>
                    <p class="text-lg font-semibold text-gray-700 mt-1">{{ setting('sales_order_prefix', 'PO-') }}{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</p>
                    <p class="text-sm text-gray-500 mt-1">Date: {{ $order->created_at->format(setting('date_format', 'Y-m-d')) }}</p>
                </div>
            </div>
            
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8 border-t border-gray-100 pt-8">
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Vendor Details</h4>
                    <p class="text-lg font-bold text-gray-900">{{ $order->supplier->name }}</p>
                    @if($order->supplier->contact_person) <p class="text-gray-600 text-sm mt-1">Attn: {{ $order->supplier->contact_person }}</p> @endif
                    @if($order->supplier->address) <p class="text-gray-600 text-sm mt-1 whitespace-pre-line">{{ $order->supplier->address }}</p> @endif
                    @if($order->supplier->email) <p class="text-gray-600 text-sm mt-1">{{ $order->supplier->email }}</p> @endif
                    @if($order->supplier->phone) <p class="text-gray-600 text-sm mt-1">{{ $order->supplier->phone }}</p> @endif
                </div>
                <div class="text-right flex flex-col justify-end items-end">
                    <div class="mb-4">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Order Status</span>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-bold rounded-full {{ $order->status === 'received' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst($order->status) }}
                            </span>
                            @if($order->approval_status === 'pending_approval')
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-bold rounded-full bg-amber-100 text-amber-800">
                                    Pending Director Approval (>$10k)
                                </span>
                            @elseif($order->approval_status === 'rejected')
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-bold rounded-full bg-red-100 text-red-800">
                                    Rejected
                                </span>
                            @endif
                            <a href="{{ route('pdf.purchaseOrder', $order->id) }}" target="_blank" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 ml-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Download PDF
                            </a>
                        </div>
                    </div>
                    @if($order->approval_status === 'approved' && $order->status === 'approved' && $order->total_amount > 0 && $order->items->count() > 0)
                        <a href="{{ route('grn.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Receive Goods (GRN)
                        </a>
                    @elseif($order->approval_status === 'pending_approval' && (auth()->user()->hasRole('Super Admin') || auth()->user()->can('approve purchase_orders')))
                        <div class="flex items-center justify-end gap-2 mt-4">
                            <button wire:click="rejectOrder" class="px-4 py-2 bg-rose-100 text-rose-700 hover:bg-rose-200 text-xs font-bold rounded-lg transition-colors">Reject Order</button>
                            <button wire:click="approveOrder" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors">Approve Order</button>
                        </div>
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
                        <select wire:model.live="product_id" id="product_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
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
                        <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quantity" value="Quantity" />
                        <x-text-input wire:model="quantity" id="quantity" type="number" min="1" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="unit_price" value="Unit Price ({{ $order->currency_code }})" />
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
                                <td class="px-6 py-4 whitespace-nowrap">{{ $currencySymbol }}{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $currencySymbol }}{{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button wire:click="removeItem({{ $item->id }})" class="text-red-600 hover:text-red-900">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right font-medium text-gray-500">Subtotal</td>
                            <td colspan="2" class="px-6 py-4 whitespace-nowrap text-left font-medium">{{ $currencySymbol }}{{ number_format($order->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-2 text-right text-gray-500">
                                GST ({{ $order->gst_percentage }}% {{ ucfirst($order->gst_type) }})
                            </td>
                            <td colspan="2" class="px-6 py-2 whitespace-nowrap text-left text-gray-500">
                                {{ $currencySymbol }}{{ number_format($order->gst_amount, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right font-bold text-gray-900 text-lg">Total</td>
                            <td colspan="2" class="px-6 py-4 whitespace-nowrap text-left font-bold text-gray-900 text-lg">
                                {{ $currencySymbol }}{{ number_format($order->total_amount, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if($order->remarks || $order->terms_and_conditions)
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-lg border border-gray-200">
                @if($order->remarks)
                <div>
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Remarks</h4>
                    <p class="text-gray-600 whitespace-pre-line text-sm">{{ $order->remarks }}</p>
                </div>
                @endif
                
                @if($order->terms_and_conditions)
                <div>
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Terms & Conditions</h4>
                    <p class="text-gray-600 whitespace-pre-line text-sm">{{ $order->terms_and_conditions }}</p>
                </div>
                @endif
            </div>
            @endif
            @else
                <p class="text-gray-500">No items in this purchase order.</p>
            @endif
        </div>
    </div>
</div>

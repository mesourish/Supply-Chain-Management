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

    public $showPreviewModal = false;
    public $selectedOrder = null;
    public $viewMode = 'table';
    public $showForm = false;

    public function rules()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'status' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required_if:items.*.is_blank,false|nullable|exists:products,id',
            'items.*.description' => 'required_if:items.*.is_blank,true|string|nullable',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function mount()
    {
        $this->gst_percentage = (float)setting('default_gst_percentage', '18');
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->items = [
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
        ];
    }

    public function updatedItems($value, $key)
    {
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            $index = $parts[0];
            $field = $parts[1];
            
            if ($field === 'product_id' && $value) {
                if (!empty($this->items[$index]['is_blank'])) {
                    return;
                }
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
        $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false];
        $this->recalculateTotals();
    }

    public function addBlankLine()
    {
        $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true];
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
                    'product_id' => empty($item['is_blank']) ? $item['product_id'] : null,
                    'description' => !empty($item['is_blank']) ? $item['description'] : null,
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
        $this->viewMode = 'table';
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
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'is_blank' => $item->product_id === null,
            ];
        }
        
        if (empty($this->items)) {
            $this->items = [
                ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
            ];
        }

        $this->recalculateTotals();
        $this->isEditing = true;
        $this->showForm = true;
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
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false]
        ];
        $this->recalculateTotals();
        $this->showForm = false;
    }

    public function toggleForm()
    {
        if ($this->showForm && !$this->isEditing) {
            $this->showForm = false;
        } else {
            $this->resetInputFields();
            $this->showForm = true;
        }
    }

    public function previewOrder($id)
    {
        $this->selectedOrder = SalesOrder::with(['customer', 'items.product'])->findOrFail($id);
        $this->showPreviewModal = true;
    }

    public function moveOrderStatus($id, $newStatus)
    {
        $order = SalesOrder::findOrFail($id);
        $oldStatus = $order->status;
        $order->status = $newStatus;
        $order->save();

        \App\Helpers\SystemLogger::log(
            'drag_kanban',
            'Sales Orders',
            "Sales Order SO-" . str_pad($order->id, 5, '0', STR_PAD_LEFT) . " status dragged/moved from '{$oldStatus}' to '{$newStatus}'."
        );

        $this->dispatch('toast', type: 'success', message: 'Sales Order status updated successfully.');
    }

    public function with()
    {
        return [
            'orders' => SalesOrder::with(['customer'])->latest()->paginate(10),
            'customers' => Customer::all(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header Actions & View Mode Toggle -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Sales Order Management</h1>
            <p class="text-xs text-gray-500 mt-1">Manage sales orders, track logistics fulfillment status, and trigger shipments directly from customer invoice drafts.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <!-- Segment Controls (List / Kanban) -->
            <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200">
                <button type="button" 
                        wire:click="$set('viewMode', 'table')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 {{ $viewMode === 'table' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                    List View
                </button>
                <button type="button" 
                        wire:click="$set('viewMode', 'kanban')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 {{ $viewMode === 'kanban' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                    Kanban Board
                </button>
            </div>

            @can('create sales_orders')
                <button type="button" wire:click="toggleForm" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors shadow-indigo-500/20">
                    {{ $showForm ? 'Close Form' : '+ New Sales Order' }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Financial KPI Summary Cards -->
    @php
        $totalSoVolume = \App\Models\SalesOrder::sum('total_amount');
        $deliveredCount = \App\Models\SalesOrder::where('status', 'delivered')->count();
        $totalCount = \App\Models\SalesOrder::count();
        $pendingFulfillment = \App\Models\SalesOrder::whereNotIn('status', ['delivered', 'cancelled'])->count();
        $fulfillmentRate = $totalCount > 0 ? ($deliveredCount / $totalCount) * 100 : 0;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 animate-fade-in">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Total SO Volume</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalSoVolume, 2) }}</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Across {{ $totalCount }} sales orders</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Delivered Orders</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $deliveredCount }}</h3>
            <span class="text-[10px] text-emerald-600 font-bold block mt-1">Successfully fulfilled to customers</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Pending Fulfillment</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $pendingFulfillment }}</h3>
            <span class="text-[10px] text-amber-600 font-bold block mt-1">Orders in processing/shipping</span>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Fulfillment Rate</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ number_format($fulfillmentRate, 1) }}%</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Percentage of orders delivered</span>
        </div>
    </div>

    <!-- Top section: Creation / Editing Form and Live Preview directly on A4 Document -->
    @if($viewMode === 'table' && $showForm)
    <div class="animate-slide-down">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">
                    {{ $isEditing ? 'Edit Sales Order' : 'Create Sales Order' }}
                </h3>
                <p class="text-xs text-slate-550">
                    {{ $isEditing ? 'Modify fields inside the sales order document layout below.' : 'Fill in the fields on the sales order document layout below.' }}
                </p>
            </div>
            <button type="button" wire:click="resetInputFields" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200 rounded-xl transition-colors" title="Close Form">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        @php
            $selectedCustomer = $customers->firstWhere('id', $customer_id);
        @endphp
        <form wire:submit.prevent="save">
            <div class="bg-white shadow-2xl border border-slate-200 p-12 relative overflow-hidden text-xs text-slate-755 font-semibold" style="min-height: 1000px; font-family: 'Inter', system-ui, sans-serif;">
                <!-- Top Accent border -->
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
                        <h1 class="text-3xl font-black text-indigo-600 uppercase tracking-wider mb-2">Sales Order</h1>
                        <div class="text-xs text-slate-550 leading-relaxed font-semibold space-y-1">
                            <div>
                                <strong>SO #:</strong> 
                                <span class="font-mono font-bold">{{ $orderId ? setting('sales_order_prefix', 'SO-') . str_pad($orderId, 5, '0', STR_PAD_LEFT) : 'DRAFT' }}</span>
                            </div>
                            <div>
                                <strong>Date:</strong> 
                                <span class="font-mono font-bold">{{ now()->format('M d, Y') }}</span>
                            </div>
                            <div>
                                <strong>Status:</strong>
                                <select wire:model="status" id="status" class="border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-extrabold uppercase text-indigo-600 p-0.5 rounded cursor-pointer" required>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Panels -->
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Bill To Customer *</div>
                        <div class="text-xs text-slate-700 leading-relaxed font-medium">
                            <select wire:model.live="customer_id" id="customer_id" class="w-full border-0 border-b border-dashed border-slate-300 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs p-1 text-slate-800 font-bold mb-2 rounded cursor-pointer" required>
                                <option value="">Select Customer...</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />

                            @if($selectedCustomer)
                                @if($selectedCustomer->contact_person) <span class="text-slate-500">Attn:</span> {{ $selectedCustomer->contact_person }}<br> @endif
                                @if($selectedCustomer->billing_address) <span class="block text-slate-600 my-1 whitespace-pre-line">{{ $selectedCustomer->billing_address }}</span> @endif
                                @if($selectedCustomer->email) <span class="text-slate-500">Email:</span> {{ $selectedCustomer->email }}<br> @endif
                                @if($selectedCustomer->phone) <span class="text-slate-500">Phone:</span> {{ $selectedCustomer->phone }} @endif
                            @else
                                <span class="text-slate-400 italic">No customer selected</span>
                            @endif
                        </div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Shipping Details</div>
                        <div class="text-xs text-slate-600 leading-relaxed font-medium">
                            @if($selectedCustomer && $selectedCustomer->shipping_address)
                                <strong>Delivery Address:</strong><br>
                                <span class="block text-slate-600 mt-1 whitespace-pre-line">{{ $selectedCustomer->shipping_address }}</span>
                            @else
                                <strong>Delivery Address:</strong><br>
                                As per standard terms.
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-8">
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
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @php $lineNum = 1; @endphp
                            @foreach($items as $index => $item)
                                @php
                                    $lineSub = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                                    $totalSub = (float)$subtotal;
                                    $lineTax = $totalSub > 0 ? ($lineSub / $totalSub) * (float)$tax_amount : 0;
                                    $lineTot = $lineSub + $lineTax;
                                @endphp
                                <tr class="hover:bg-slate-50/30 transition-colors">
                                    <td class="p-3 text-slate-400">{{ $lineNum++ }}</td>
                                    <td class="p-3">
                                        @if(!empty($item['is_blank']))
                                            <input type="text" wire:model.live="items.{{ $index }}.description" class="w-full border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-bold p-1 text-slate-800 rounded" placeholder="Enter custom item or service description..." required>
                                            <x-input-error :messages="$errors->get('items.'.$index.'.description')" class="mt-1" />
                                        @else
                                            <select wire:model.live="items.{{ $index }}.product_id" class="w-full border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-bold p-1 text-slate-700 rounded cursor-pointer" required>
                                                <option value="">Select Product...</option>
                                                @foreach(\App\Models\Product::all() as $p)
                                                    <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('items.'.$index.'.product_id')" class="mt-1" />
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        <input type="number" min="0.01" step="0.01" wire:model.live="items.{{ $index }}.quantity" class="w-16 text-center border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono font-bold p-1 text-slate-800 rounded" required>
                                        <x-input-error :messages="$errors->get('items.'.$index.'.quantity')" class="mt-1" />
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end">
                                            <span class="text-slate-400 font-mono mr-1">{{ setting('currency_symbol', '$') }}</span>
                                            <input type="number" step="0.01" min="0" wire:model.live="items.{{ $index }}.unit_price" class="w-20 text-right border-0 border-b border-dashed border-slate-200 focus:border-indigo-600 focus:ring-0 focus:bg-indigo-50/30 bg-transparent text-xs font-mono font-bold p-1 text-slate-800 rounded" required>
                                            <x-input-error :messages="$errors->get('items.'.$index.'.unit_price')" class="mt-1" />
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
                                            <button type="button" wire:click="removeItemLine({{ $index }})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    
                    <!-- Row Addition buttons directly under the table -->
                    <div class="flex items-center gap-3 mt-4 justify-start font-bold">
                        <button type="button" wire:click="addBlankLine" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-755 text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1">
                            + Add Blank Row
                        </button>
                        <button type="button" wire:click="addItemLine" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-100 transition-colors flex items-center gap-1">
                            + Add Product Line
                        </button>
                    </div>
                </div>

                <!-- Notes & Totals -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start border-t border-slate-150 pt-6">
                    <div class="md:col-span-7 bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Remarks / Special Instructions</div>
                            <p class="text-xs text-slate-600 leading-relaxed font-semibold">
                                Thank you for your business. Please process payment according to the terms listed.
                            </p>
                        </div>
                        <div class="border-t border-slate-200 pt-3 flex flex-wrap items-center gap-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-bold text-slate-600">
                                <input type="checkbox" wire:model.live="apply_gst" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                <span class="text-xs font-bold text-slate-700">Apply GST Tax</span>
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
                    </div>
                    <div class="md:col-span-5">
                        <table class="w-full text-xs font-semibold text-slate-600">
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr>
                                    <td class="py-2.5 text-left text-slate-400">Subtotal:</td>
                                    <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ setting('currency_symbol', '$') }}{{ number_format($subtotal, 2) }}</td>
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
                                    <td class="py-3 text-left font-black text-slate-900 text-sm">Total Due:</td>
                                    <td class="py-3 text-right font-mono font-black text-indigo-600 text-lg">{{ setting('currency_symbol', '$') }}{{ number_format($total_amount, 2) }}</td>
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

            <!-- Premium High-Visibility Action Toolbar -->
            <div class="mt-8 p-4 bg-slate-50/90 backdrop-blur rounded-2xl border border-slate-200/80 shadow-lg flex justify-end gap-3 no-print">
                <button type="button" wire:click="resetInputFields" class="px-6 py-3 bg-white hover:bg-slate-50 text-slate-705 text-xs font-black uppercase tracking-wider rounded-xl border border-slate-300 shadow-sm transition-all active:scale-[0.98]">
                    Cancel &amp; Close
                </button>
                <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md hover:shadow-indigo-500/20 transition-all active:scale-[0.98] cursor-pointer">
                    {{ $isEditing ? 'Save & Update Sales Order' : 'Confirm & Create Sales Order' }}
                </button>
            </div>
        </form>
    </div>
    @endif

    <!-- Bottom List Table -->
    @if($viewMode === 'table')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-3xl border border-slate-150">
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <a href="{{ route('pdf.salesOrder', $order->id) }}" target="_blank" class="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors inline-flex items-center" title="PDF Preview & Download">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                    <button type="button" wire:click="edit({{ $order->id }})" class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-lg transition-colors inline-flex items-center" title="Edit Sales Order">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button type="button" wire:click="delete({{ $order->id }})" wire:confirm="Are you sure?" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors inline-flex items-center" title="Delete Sales Order">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
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
    @else
        <!-- Sales Order Kanban Pipeline View -->
        <div class="flex overflow-x-auto gap-6 pb-6 select-none scrollbar-thin" style="scrollbar-width: thin; -webkit-overflow-scrolling: touch;">
            @foreach([
                'pending' => ['name' => 'Pending Order', 'bg' => 'bg-slate-100/50', 'border' => 'border-slate-200', 'text' => 'text-slate-600'],
                'processing' => ['name' => 'Processing', 'bg' => 'bg-amber-50/30', 'border' => 'border-amber-100', 'text' => 'text-amber-600'],
                'shipped' => ['name' => 'Shipped 🚚', 'bg' => 'bg-blue-50/30', 'border' => 'border-blue-100', 'text' => 'text-blue-600'],
                'delivered' => ['name' => 'Delivered 🎉', 'bg' => 'bg-emerald-50/30', 'border' => 'border-emerald-100', 'text' => 'text-emerald-600'],
                'cancelled' => ['name' => 'Cancelled', 'bg' => 'bg-rose-50/20', 'border' => 'border-rose-100', 'text' => 'text-rose-600']
            ] as $statusKey => $statusVal)
                
                @php
                    $statusOrders = App\Models\SalesOrder::with(['customer', 'items.product'])->where('status', $statusKey)->get();
                    $statusTotal = $statusOrders->sum('total_amount');
                @endphp

                <div x-data="{ draggingOver: false }"
                     x-on:dragenter.prevent="draggingOver = true"
                     x-on:dragleave.prevent="draggingOver = false"
                     x-on:dragover.prevent=""
                     x-on:drop="draggingOver = false; const orderId = event.dataTransfer.getData('text/plain'); @this.moveOrderStatus(orderId, '{{ $statusKey }}')"
                     class="flex-shrink-0 w-[290px] lg:w-[310px] rounded-3xl p-4 transition-all duration-200 border space-y-4 {{ $statusVal['bg'] }} {{ $statusVal['border'] }}"
                     :class="{ 'ring-2 ring-indigo-600 bg-indigo-50/15 border-indigo-200 shadow-md scale-[1.01]': draggingOver }"
                >
                    <!-- Column Header -->
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <div>
                            <h4 class="text-sm font-black text-gray-900">{{ $statusVal['name'] }}</h4>
                            <span class="text-[10px] text-gray-400 font-bold font-mono">{{ $statusOrders->count() }} orders</span>
                        </div>
                        <span class="text-xs font-black {{ $statusVal['text'] }} font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($statusTotal, 0) }}</span>
                    </div>

                    <!-- Column Cards list -->
                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        @forelse($statusOrders as $order)
                            <div draggable="true"
                                 x-on:dragstart="event.dataTransfer.setData('text/plain', {{ $order->id }})"
                                 wire:click="edit({{ $order->id }})"
                                 class="bg-white p-4 rounded-2xl border border-gray-150 shadow-sm relative group hover:shadow-md hover:border-indigo-600 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"
                            >
                                <div class="flex justify-between items-start gap-1">
                                    <div class="text-xs font-extrabold text-gray-900 leading-snug pr-4">SO-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</div>
                                    <a href="{{ route('pdf.salesOrder', $order->id) }}" target="_blank" onclick="event.stopPropagation()" class="p-1 text-indigo-600 hover:bg-indigo-50 rounded" title="PDF Preview">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                </div>
                                <div class="text-[10px] font-bold text-indigo-600 mt-0.5 truncate">{{ $order->customer->name ?? 'N/A' }}</div>
                                
                                <!-- Items preview -->
                                <div class="mt-2 text-[9px] text-gray-400 border-t border-slate-100 pt-2 space-y-0.5 max-h-[60px] overflow-hidden font-medium">
                                    @foreach($order->items as $itm)
                                        <div class="truncate">{{ $itm->quantity }}x {{ $itm->product ? $itm->product->name : 'Item' }}</div>
                                    @endforeach
                                </div>

                                <!-- Total Value -->
                                <div class="mt-3 flex items-baseline justify-between border-t border-slate-50 pt-2">
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">Total:</span>
                                    <span class="text-xs font-mono font-black text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</span>
                                </div>
                                
                                <div class="border-t border-gray-50 pt-2 mt-2.5 flex items-center justify-end text-[9px] font-bold" onclick="event.stopPropagation()">
                                    <select 
                                        onchange="event.stopPropagation(); @this.moveOrderStatus({{ $order->id }}, this.value)"
                                        onclick="event.stopPropagation()"
                                        class="p-0.5 text-[8px] bg-slate-50 border-gray-200 text-gray-600 rounded focus:ring-0 focus:border-indigo-600"
                                    >
                                        <option value="">Move...</option>
                                        @foreach(['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $v)
                                            <option value="{{ $k }}" {{ $order->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-gray-400 text-xs italic font-medium">Empty Lane</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

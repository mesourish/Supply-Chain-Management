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

    // Advanced ERP Fields
    public $contact_person_id = null;
    public $billing_address_id = null;
    public $shipping_address_id = null;
    public $expected_delivery = null;

    public $items = [];
    public $showPreviewModal = false;
    public $selectedPo = null;
    public $viewMode = 'table';
    public $supplierNotes = '';
    public $showForm = false;

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
            'contact_person_id' => 'nullable|exists:contact_persons,id',
            'billing_address_id' => 'nullable|exists:addresses,id',
            'shipping_address_id' => 'nullable|exists:addresses,id',
            'expected_delivery' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required_if:items.*.is_blank,false|nullable|exists:products,id',
            'items.*.description' => 'required_if:items.*.is_blank,true|string|nullable',
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
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false, 'search_query' => '', 'show_dropdown' => false]
        ];

        $editId = request()->query('edit_id');
        if ($editId) {
            $this->edit($editId);
        }
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
                    $this->items[$index]['search_query'] = $product->sku . ' - ' . $product->name;
                }
            }
        }
        $this->recalculateTotals();
    }

    public function selectProduct($index, $productId)
    {
        $product = \App\Models\Product::find($productId);
        if ($product) {
            $this->items[$index]['product_id'] = $productId;
            $this->items[$index]['search_query'] = $product->sku . ' - ' . $product->name;
            $this->items[$index]['description'] = $product->name;
            $this->items[$index]['show_dropdown'] = false;
            
            // Set unit price (checking if supplier has custom price)
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
        $this->recalculateTotals();
    }

    public function updatedSupplierId()
    {
        if ($this->supplier_id) {
            $supplier = Supplier::with(['contactPersons', 'addresses'])->find($this->supplier_id);
            if ($supplier) {
                $primaryContact = $supplier->contactPersons->where('is_primary', true)->first();
                $this->contact_person_id = $primaryContact ? $primaryContact->id : null;
                
                $billing = $supplier->addresses->where('type', 'billing')->first();
                $this->billing_address_id = $billing ? $billing->id : null;
                
                $shipping = $supplier->addresses->where('type', 'shipping')->first();
                $this->shipping_address_id = $shipping ? $shipping->id : null;
            }
        } else {
            $this->contact_person_id = null;
            $this->billing_address_id = null;
            $this->shipping_address_id = null;
        }

        foreach ($this->items as $index => $item) {
            if (!empty($item['is_blank'])) {
                continue;
            }
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
        $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false, 'search_query' => '', 'show_dropdown' => false];
        $this->recalculateTotals();
    }

    public function addBlankLine()
    {
        $this->items[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => true, 'search_query' => '', 'show_dropdown' => false];
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

        $isNew = !$this->orderId;

        $approvalStatus = $this->orderId
            ? PurchaseOrder::find($this->orderId)->approval_status ?? 'pending_approval'
            : 'pending_approval';

        \DB::transaction(function () use ($approvalStatus, $isNew) {
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
                    'contact_person_id' => $this->contact_person_id,
                    'billing_address_id' => $this->billing_address_id,
                    'shipping_address_id' => $this->shipping_address_id,
                    'expected_delivery' => $this->expected_delivery ? date('Y-m-d H:i:s', strtotime($this->expected_delivery)) : null,
                ]
            );

            // Save items
            $po->items()->delete();
            foreach ($this->items as $item) {
                $po->items()->create([
                    'product_id' => empty($item['is_blank']) ? $item['product_id'] : null,
                    'description' => !empty($item['is_blank']) ? $item['description'] : null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);

                // Auto attach to supplier if not already attached
                if (empty($item['is_blank']) && $item['product_id']) {
                    $isAttached = $po->supplier->products()->where('product_id', $item['product_id'])->exists();
                    if (!$isAttached) {
                        $po->supplier->products()->attach($item['product_id'], [
                            'price' => $item['unit_price']
                        ]);
                    }
                }
            }

            // --- AUTO SYNC GRN & STOCK & AP ---
            if (!$isNew) {
                $hasGrn = \App\Models\GoodsReceiptNote::where('purchase_order_id', $po->id)->exists();
                if ($hasGrn) {
                    $grns = \App\Models\GoodsReceiptNote::where('purchase_order_id', $po->id)->get();
                    $grnIds = $grns->pluck('id')->toArray();

                    // 1. Reverse bin stock & delete inventory transactions
                    $txs = \App\Models\InventoryTransaction::where('reference_type', \App\Models\GoodsReceiptNote::class)
                        ->whereIn('reference_id', $grnIds)
                        ->get();

                    foreach ($txs as $tx) {
                        $binStock = \App\Models\BinProductStock::where('warehouse_bin_id', $tx->to_bin_id)
                            ->where('product_id', $tx->product_id)
                            ->first();
                        if ($binStock) {
                            $binStock->decrement('quantity', $tx->quantity);
                        }
                        $tx->forceDelete();
                    }

                    // Get previous bin or fallback
                    $binId = null;
                    foreach ($txs as $tx) {
                        if ($tx->to_bin_id) {
                            $binId = $tx->to_bin_id;
                            break;
                        }
                    }
                    if (!$binId) {
                        $binId = \App\Models\WarehouseBin::first()->id;
                    }

                    // 2. Delete old GRNs
                    \App\Models\GoodsReceiptNote::where('purchase_order_id', $po->id)->forceDelete();

                    // 3. Delete old Account Payables
                    \App\Models\AccountPayable::where('purchase_order_id', $po->id)->delete();

                    // 4. Re-create new GRN matching updated items
                    if ($binId) {
                        $newGrn = \App\Models\GoodsReceiptNote::create([
                            'purchase_order_id' => $po->id,
                            'user_id' => auth()->id(),
                            'status' => 'received',
                            'notes' => 'Auto-updated after Purchase Order modification.',
                        ]);

                        $totalAmountReceived = 0;
                        // Reload PO items relations to get the newly created ones
                        $po->load('items');
                        foreach ($po->items as $poItem) {
                            $poItem->update(['received_quantity' => $poItem->quantity]);

                            if ($poItem->product_id !== null) {
                                \App\Models\InventoryTransaction::create([
                                    'product_id' => $poItem->product_id,
                                    'from_bin_id' => null,
                                    'to_bin_id' => $binId,
                                    'type' => 'IN',
                                    'quantity' => $poItem->quantity,
                                    'reference_type' => \App\Models\GoodsReceiptNote::class,
                                    'reference_id' => $newGrn->id,
                                    'notes' => 'Auto-received via GRN #' . $newGrn->id,
                                    'user_id' => auth()->id(),
                                ]);

                                // Update Physical Bin Stock
                                $binStock = \App\Models\BinProductStock::firstOrCreate(
                                    ['warehouse_bin_id' => $binId, 'product_id' => $poItem->product_id],
                                    ['quantity' => 0, 'unit_cost' => $poItem->unit_price]
                                );
                                $binStock->increment('quantity', $poItem->quantity);
                            }

                            $totalAmountReceived += ($poItem->quantity * $poItem->unit_price);
                        }

                        // Re-create Account Payable
                        if ($totalAmountReceived > 0) {
                            \App\Models\AccountPayable::create([
                                'purchase_order_id' => $po->id,
                                'supplier_id' => $po->supplier_id,
                                'amount' => $totalAmountReceived,
                                'status' => 'unpaid',
                            ]);
                        }

                        // Set PO status to received
                        $po->status = 'received';
                        $po->save();
                    }
                }
            }
            // ----------------------------------

            // Real-time timeline log logging
            if ($isNew) {
                \App\Models\PurchaseOrderLog::create([
                    'purchase_order_id' => $po->id,
                    'user_id' => auth()->id(),
                    'action' => 'created',
                    'notes' => 'Purchase Order draft created successfully.',
                ]);
                \App\Helpers\SystemLogger::log(
                    'po_created',
                    'Purchase Orders',
                    "Purchase Order PO-" . str_pad($po->id, 5, '0', STR_PAD_LEFT) . " created as draft."
                );
            } else {
                \App\Models\PurchaseOrderLog::create([
                    'purchase_order_id' => $po->id,
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'notes' => 'Purchase Order details updated successfully.',
                ]);
                \App\Helpers\SystemLogger::log(
                    'po_updated',
                    'Purchase Orders',
                    "Purchase Order PO-" . str_pad($po->id, 5, '0', STR_PAD_LEFT) . " updated."
                );
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
        
        $this->contact_person_id = $order->contact_person_id;
        $this->billing_address_id = $order->billing_address_id;
        $this->shipping_address_id = $order->shipping_address_id;
        $this->expected_delivery = $order->expected_delivery ? $order->expected_delivery->format('Y-m-d') : null;
        
        $this->items = [];
        foreach ($order->items as $item) {
            $productName = '';
            if ($item->product_id) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $productName = $product->sku . ' - ' . $product->name;
                }
            }
            $this->items[] = [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'is_blank' => $item->product_id === null,
                'search_query' => $productName,
                'show_dropdown' => false,
            ];
        }
        
        if (empty($this->items)) {
            $this->items = [
                ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false, 'search_query' => '', 'show_dropdown' => false]
            ];
        }

        $this->recalculateTotals();
        $this->viewMode = 'table';
        $this->isEditing = true;
        $this->showForm = true;
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

        $this->contact_person_id = null;
        $this->billing_address_id = null;
        $this->shipping_address_id = null;
        $this->expected_delivery = null;

        $this->items = [
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0.00, 'is_blank' => false, 'search_query' => '', 'show_dropdown' => false]
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

    public function previewPo($id)
    {
        $this->selectedPo = PurchaseOrder::with(['supplier', 'items.product'])->findOrFail($id);
        $this->showPreviewModal = true;
    }

    public function approveLevel1($id)
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('approve_l1 purchase_orders')) abort(403);
        $po = PurchaseOrder::findOrFail($id);
        $po->update(['approval_status' => 'finance_approved', 'approval_notes' => 'Level 1 Approved by Finance']);
        
        \App\Models\PurchaseOrderLog::create([
            'purchase_order_id' => $id,
            'user_id' => auth()->id(),
            'action' => 'finance_approved',
            'notes' => 'Level 1 Approved by Finance',
        ]);

        \App\Helpers\SystemLogger::log('approve_l1', 'Purchase Orders', "Purchase Order PO-" . str_pad($id, 5, '0', STR_PAD_LEFT) . " L1 Finance Approved.");

        $this->dispatch('toast', type: 'success', message: 'PO Level 1 Approved by Finance.');
    }

    public function approveLevel2($id)
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('approve_l2 purchase_orders')) abort(403);
        $po = PurchaseOrder::findOrFail($id);
        $po->update(['approval_status' => 'approved', 'approval_notes' => 'Level 2 Final Approved by Director', 'status' => 'approved']);
        
        \App\Models\PurchaseOrderLog::create([
            'purchase_order_id' => $id,
            'user_id' => auth()->id(),
            'action' => 'approved',
            'notes' => 'Level 2 Final Approved by Director',
        ]);

        \App\Helpers\SystemLogger::log('approve_l2', 'Purchase Orders', "Purchase Order PO-" . str_pad($id, 5, '0', STR_PAD_LEFT) . " L2 Director Approved.");

        $this->dispatch('toast', type: 'success', message: 'PO Final Approved by Director.');
    }

    public function rejectOrder($id)
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('approve_l1 purchase_orders') && !auth()->user()->can('approve_l2 purchase_orders')) abort(403);
        $po = PurchaseOrder::findOrFail($id);
        $po->update(['approval_status' => 'rejected', 'approval_notes' => 'Rejected by Director', 'status' => 'cancelled']);
        
        \App\Models\PurchaseOrderLog::create([
            'purchase_order_id' => $id,
            'user_id' => auth()->id(),
            'action' => 'rejected',
            'notes' => 'Rejected by Director',
        ]);

        \App\Helpers\SystemLogger::log('reject', 'Purchase Orders', "Purchase Order PO-" . str_pad($id, 5, '0', STR_PAD_LEFT) . " Rejected.");

        $this->dispatch('toast', type: 'error', message: 'PO Rejected.');
    }

    public function movePoStatus($id, $newStatus)
    {
        $po = PurchaseOrder::findOrFail($id);
        $oldStatus = $po->status;
        $po->status = $newStatus;
        $po->save();

        \App\Models\PurchaseOrderLog::create([
            'purchase_order_id' => $id,
            'user_id' => auth()->id(),
            'action' => $newStatus,
            'notes' => "PO status changed from '{$oldStatus}' to '{$newStatus}' via Kanban drag.",
        ]);

        \App\Helpers\SystemLogger::log(
            'drag_kanban',
            'Purchase Orders',
            "Purchase Order PO-" . str_pad($po->id, 5, '0', STR_PAD_LEFT) . " status dragged/moved from '{$oldStatus}' to '{$newStatus}'."
        );

        $this->dispatch('toast', type: 'success', message: 'PO status updated successfully.');
    }

    public function logSupplierAction($action, $notes = '')
    {
        if (!$this->orderId) return;

        if ($action === 'supplier_want_changes') {
            $notes = $this->supplierNotes;
        }

        \App\Models\PurchaseOrderLog::create([
            'purchase_order_id' => $this->orderId,
            'user_id' => auth()->id(),
            'action' => $action,
            'notes' => $notes ?: null,
        ]);

        \App\Helpers\SystemLogger::log(
            'po_supplier_action',
            'Purchase Orders',
            "Purchase Order PO-" . str_pad($this->orderId, 5, '0', STR_PAD_LEFT) . " logged supplier action: '" . str_replace('_', ' ', $action) . "'" . ($notes ? " with notes: '{$notes}'" : "") . "."
        );

        $this->supplierNotes = '';
        $this->dispatch('toast', type: 'success', message: 'Supplier action logged successfully.');
    }

    public function with()
    {
        return [
            'orders' => PurchaseOrder::with(['supplier'])->latest()->paginate(10),
            'suppliers' => Supplier::where('is_active', true)->get(),
            'currencies' => \App\Models\Currency::all(),
            'gst_percentages' => SystemConstant::where('type', 'gst_percentage')->where('is_active', true)->orderBy('value')->get(),
            'allProducts' => \App\Models\Product::with('binStocks')->orderBy('sku')->get(),
            'supplierContacts' => $this->supplier_id ? \App\Models\Supplier::find($this->supplier_id)?->contactPersons ?? collect() : collect(),
            'supplierAddresses' => $this->supplier_id ? \App\Models\Supplier::find($this->supplier_id)?->addresses ?? collect() : collect(),
        ];
    }
}; ?>


<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header Actions & View Mode Toggle -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Purchase Order Command</h1>
            <p class="text-xs text-gray-500 mt-1">Configure tax schedules, monitor real-time stock alerts, dynamic exchange rates, and supplier workflows.</p>
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

            @can('create purchase_orders')
                <button type="button" wire:click="toggleForm" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-750 text-white text-sm font-bold rounded-xl shadow-md transition-all active:scale-95 shadow-indigo-500/20">
                    {{ $showForm ? 'Close Form' : '+ New Purchase Order' }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Financial KPI Summary Cards -->
    @php
        $totalPoVolume = \App\Models\PurchaseOrder::sum('total_amount');
        $approvedCount = \App\Models\PurchaseOrder::where('approval_status', 'approved')->count();
        $totalCount = \App\Models\PurchaseOrder::count();
        $pendingApprovals = \App\Models\PurchaseOrder::whereIn('approval_status', ['pending_approval', 'finance_approved'])->count();
        $approvalRate = $totalCount > 0 ? ($approvedCount / $totalCount) * 100 : 0;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 animate-fade-in">
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Total PO Volume</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalPoVolume, 2) }}</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Across {{ $totalCount }} purchase orders</span>
        </div>
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Approved (L2) Orders</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $approvedCount }}</h3>
            <span class="text-[10px] text-emerald-600 font-bold block mt-1">Ready for supply chain fulfillment</span>
        </div>
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Pending Approvals</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $pendingApprovals }}</h3>
            <span class="text-[10px] text-amber-600 font-bold block mt-1">Awaiting L1/L2 sign-off</span>
        </div>
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">PO Approval Rate</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ number_format($approvalRate, 1) }}%</h3>
            <span class="text-[10px] text-gray-400 font-bold block mt-1">Percentage of POs approved</span>
        </div>
    </div>

    <!-- Top section: Creation / Editing Form and Live Preview -->
    @if($viewMode === 'table' && $showForm)
    <div class="animate-slide-down space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">
                    {{ $isEditing ? 'Modify Purchase Order' : 'Create Purchase Order' }}
                </h3>
                <p class="text-xs text-slate-450">
                    Input supplier specifications, terms, and line items.
                </p>
            </div>
            <button type="button" wire:click="resetInputFields" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition" title="Close Form">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        @php
            $selectedCurrencySymbol = \App\Models\Currency::where('code', $currency_code)->value('symbol') ?? '$';
            $selectedSupplier = $suppliers->firstWhere('id', $supplier_id);
        @endphp

        <form wire:submit.prevent="save" class="space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Main PO Form Area -->
                <div class="{{ $isEditing ? 'lg:col-span-8' : 'lg:col-span-12' }} space-y-8">
                    
                    <!-- Form block: Metadata -->
                    <div class="bg-white shadow-xl rounded-3xl border border-slate-200/80 p-8 space-y-6">
                        <div class="border-b border-slate-100 pb-3">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">1. Primary Information</h4>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                            <!-- Supplier Selection -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">Supplier / Vendor *</label>
                                <select wire:model.live="supplier_id" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none cursor-pointer" required>
                                    <option value="">Select Supplier...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                            </div>

                            <!-- Contact Person -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">Primary Contact Person</label>
                                <select wire:model="contact_person_id" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none cursor-pointer">
                                    <option value="">Select Contact...</option>
                                    @foreach($supplierContacts as $contact)
                                        <option value="{{ $contact->id }}">{{ $contact->name }} ({{ $contact->designation }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('contact_person_id')" class="mt-1" />
                            </div>

                            <!-- Expected Delivery Date -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">Expected Delivery Date</label>
                                <input type="date" wire:model="expected_delivery" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none">
                                <x-input-error :messages="$errors->get('expected_delivery')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                            <!-- Billing Address -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">Billing Address</label>
                                <select wire:model="billing_address_id" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none cursor-pointer">
                                    <option value="">Select Address...</option>
                                    @foreach($supplierAddresses->where('type', 'billing') as $addr)
                                        <option value="{{ $addr->id }}">{{ $addr->address_line_1 }} ({{ $addr->city }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('billing_address_id')" class="mt-1" />
                            </div>

                            <!-- Shipping Address -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">Shipping Address</label>
                                <select wire:model="shipping_address_id" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none cursor-pointer">
                                    <option value="">Select Address...</option>
                                    @foreach($supplierAddresses->where('type', 'shipping') as $addr)
                                        <option value="{{ $addr->id }}">{{ $addr->address_line_1 }} ({{ $addr->city }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('shipping_address_id')" class="mt-1" />
                            </div>

                            <!-- Currency -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">Billing Currency</label>
                                <select wire:model.live="currency_code" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none cursor-pointer" required>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->code }}">{{ $currency->code }} ({{ $currency->symbol }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('currency_code')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                            <!-- Status -->
                            <div>
                                <label class="block text-slate-500 mb-1.5 font-bold">PO Lifecycle Status *</label>
                                <select wire:model="status" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none cursor-pointer" required>
                                    <option value="draft">Draft</option>
                                    <option value="approved">Approved</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="received">Received</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-1" />
                            </div>

                            <!-- Supplier Card Preview (If Selected) -->
                            @if($selectedSupplier)
                                <div class="bg-indigo-50/40 border border-indigo-100 rounded-2xl p-4 flex flex-col justify-center space-y-1">
                                    <span class="text-[10px] text-indigo-400 font-extrabold uppercase">Supplier Summary</span>
                                    <p class="font-extrabold text-slate-800 text-xs">{{ $selectedSupplier->name }}</p>
                                    <p class="text-slate-500 text-[10px]">Primary Contact: {{ $selectedSupplier->contact_person ?? 'N/A' }} | Email: {{ $selectedSupplier->email ?? 'N/A' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Form block: Line Items Grid -->
                    <div class="bg-white shadow-xl rounded-3xl border border-slate-200/80 p-8 space-y-6">
                        <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">2. Line Items</h4>
                            <span class="text-[10px] bg-slate-100 text-slate-550 border border-slate-200 px-3 py-1 rounded-full font-bold">Line Items: {{ count($items) }}</span>
                        </div>

                        <!-- Data Grid Table -->
                        <div class="overflow-visible">
                            <table class="w-full border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                        <th class="p-3 text-left w-8">#</th>
                                        <th class="p-3 text-left">Product SKU / Custom Description</th>
                                        <th class="p-3 text-center w-24">Quantity</th>
                                        <th class="p-3 text-right w-28">Unit Price</th>
                                        <th class="p-3 text-right w-24">Subtotal</th>
                                        <th class="p-3 text-center w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium">
                                    @foreach($items as $index => $item)
                                        @php
                                            $lineSub = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                                        @endphp
                                        <tr class="hover:bg-slate-50/20 transition-colors">
                                            <td class="p-3 text-slate-400 font-bold">{{ $index + 1 }}</td>
                                            
                                            <!-- Product Search query autocomplete -->
                                            <td class="p-3 overflow-visible">
                                                @if(!empty($item['is_blank']))
                                                    <input type="text" wire:model.live="items.{{ $index }}.description" class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-xl p-2.5 text-xs text-slate-800 font-bold bg-white" placeholder="Type custom service description..." required>
                                                    <x-input-error :messages="$errors->get('items.'.$index.'.description')" class="mt-1" />
                                                @else
                                                    <div class="relative w-full">
                                                        <input type="text"
                                                               wire:model.live="items.{{ $index }}.search_query"
                                                               wire:focus="$set('items.{{ $index }}.show_dropdown', true)"
                                                               class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-xl p-2.5 text-xs text-slate-800 font-bold bg-white"
                                                               placeholder="Search product SKU or name..."
                                                               required>
                                                        
                                                        @if(!empty($item['show_dropdown']))
                                                            @php
                                                                $q = strtolower($item['search_query'] ?? '');
                                                                $searchResults = $allProducts->filter(function($p) use ($q) {
                                                                    return str_contains(strtolower($p->name), $q) || str_contains(strtolower($p->sku), $q);
                                                                })->take(5);
                                                            @endphp
                                                            <div class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-56 overflow-y-auto"
                                                                 @click.away="@this.set('items.{{ $index }}.show_dropdown', false)">
                                                                @forelse($searchResults as $p)
                                                                    @php
                                                                        $qoh = $p->binStocks->sum('quantity');
                                                                    @endphp
                                                                    <button type="button"
                                                                            wire:click="selectProduct({{ $index }}, {{ $p->id }})"
                                                                            class="w-full text-left px-4 py-2.5 hover:bg-slate-50 transition-colors flex items-center justify-between text-xs border-b border-slate-50 last:border-0">
                                                                        <div>
                                                                            <span class="font-extrabold text-slate-805 block">{{ $p->sku }} - {{ $p->name }}</span>
                                                                            <span class="text-[9px] text-slate-400 font-semibold">{{ $p->category }} | Cost: {{ $selectedCurrencySymbol }}{{ number_format($p->cost_price, 2) }}</span>
                                                                        </div>
                                                                        <div class="text-right">
                                                                            @if($qoh <= $p->reorder_level)
                                                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-50 text-rose-600 border border-rose-150">Low: {{ $qoh }}</span>
                                                                            @else
                                                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-150">Stock: {{ $qoh }}</span>
                                                                            @endif
                                                                        </div>
                                                                    </button>
                                                                @empty
                                                                    <div class="p-4 text-center text-xs text-slate-400 italic">No products found</div>
                                                                @endforelse
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <x-input-error :messages="$errors->get('items.'.$index.'.product_id')" class="mt-1" />
                                                @endif
                                            </td>

                                            <!-- Quantity -->
                                            <td class="p-3 text-center">
                                                <input type="number" min="0.01" step="0.01" wire:model.live="items.{{ $index }}.quantity" class="w-20 text-center border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-xl p-2.5 text-xs font-mono font-bold bg-white text-slate-850" required>
                                                <x-input-error :messages="$errors->get('items.'.$index.'.quantity')" class="mt-1" />
                                            </td>

                                            <!-- Unit Price -->
                                            <td class="p-3 text-right">
                                                <div class="flex items-center justify-end relative">
                                                    <span class="absolute left-3 text-slate-400 font-mono text-xs">{{ $selectedCurrencySymbol }}</span>
                                                    <input type="number" step="0.01" min="0" wire:model.live="items.{{ $index }}.unit_price" class="w-28 text-right border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-xl p-2.5 pl-8 text-xs font-mono font-bold bg-white text-slate-850" required>
                                                </div>
                                                <x-input-error :messages="$errors->get('items.'.$index.'.unit_price')" class="mt-1" />
                                            </td>

                                            <!-- Subtotal calculated dynamically -->
                                            <td class="p-3 text-right text-slate-700 font-mono font-bold text-xs">
                                                {{ $selectedCurrencySymbol }}{{ number_format($lineSub, 2) }}
                                            </td>

                                            <!-- Delete line button -->
                                            <td class="p-3 text-center">
                                                @if(count($items) > 1)
                                                    <button type="button" wire:click="removeItemLine({{ $index }})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-2 rounded-xl transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Line Buttons -->
                        <div class="flex items-center gap-3 justify-start pt-2 font-bold">
                            <button type="button" wire:click="addItemLine" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-750 text-white text-xs font-bold rounded-xl shadow transition flex items-center gap-1.5">
                                + Add Product Line
                            </button>
                            <button type="button" wire:click="addBlankLine" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl shadow-sm border border-slate-200 transition flex items-center gap-1.5">
                                + Add Custom Service Line
                            </button>
                        </div>
                    </div>

                    <!-- Form block: Terms, Remarks & Tax Summary -->
                    <div class="bg-white shadow-xl rounded-3xl border border-slate-200/80 p-8 space-y-6">
                        <div class="border-b border-slate-100 pb-3">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">3. Notes, Terms &amp; Totals</h4>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
                            
                            <!-- Remarks / Terms config -->
                            <div class="md:col-span-7 space-y-4 text-xs">
                                <div>
                                    <label class="block text-slate-500 mb-1.5 font-bold">Remarks / Special Instructions</label>
                                    <textarea wire:model="remarks" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none" placeholder="Internal remarks or special courier shipping notes..."></textarea>
                                    <x-input-error :messages="$errors->get('remarks')" class="mt-1" />
                                </div>
                                
                                <div>
                                    <label class="block text-slate-500 mb-1.5 font-bold">Terms &amp; Conditions</label>
                                    <textarea wire:model="terms_and_conditions" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-850 rounded-xl p-3 font-semibold focus:border-indigo-500 focus:ring-0 focus:outline-none" placeholder="Standard payment and delivery terms..."></textarea>
                                    <x-input-error :messages="$errors->get('terms_and_conditions')" class="mt-1" />
                                </div>

                                <!-- GST Settings -->
                                <div class="bg-slate-50 border border-slate-150 rounded-2xl p-4 flex flex-wrap items-center gap-4 justify-between">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">GST Tax configuration</span>
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <span class="text-[10px] text-slate-400 uppercase font-bold mr-1.5">Tax Type:</span>
                                            <select wire:model.live="gst_type" class="border border-slate-200 bg-white text-xs font-bold rounded-xl p-1.5 focus:border-indigo-500 focus:ring-0 cursor-pointer">
                                                <option value="exclusive">Exclusive</option>
                                                <option value="inclusive">Inclusive</option>
                                            </select>
                                        </div>
                                        <div class="border-l border-slate-200 pl-4">
                                            <span class="text-[10px] text-slate-400 uppercase font-bold mr-1.5">Tax Rate:</span>
                                            <select wire:model.live="gst_percentage" class="border border-slate-200 bg-white text-xs font-bold rounded-xl p-1.5 focus:border-indigo-500 focus:ring-0 cursor-pointer">
                                                <option value="0">0%</option>
                                                @foreach($gst_percentages as $gst)
                                                    <option value="{{ $gst->value }}">{{ $gst->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary calculation panel -->
                            <div class="md:col-span-5 bg-indigo-50/30 border border-indigo-100 rounded-3xl p-6 space-y-4">
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider border-b border-indigo-100 pb-2">Financial Summary</h4>
                                <table class="w-full text-xs font-semibold text-slate-600">
                                    <tbody class="divide-y divide-indigo-100/50">
                                        <tr>
                                            <td class="py-2.5 text-left text-slate-500">Subtotal:</td>
                                            <td class="py-2.5 text-right font-mono font-bold text-slate-800">{{ $selectedCurrencySymbol }}{{ number_format($subtotal, 2) }}</td>
                                        </tr>
                                        @if($gst_amount > 0)
                                            <tr>
                                                <td class="py-2.5 text-left text-slate-500">GST Tax ({{ $gst_percentage }}% {{ ucfirst($gst_type) }}):</td>
                                                <td class="py-2.5 text-right font-mono font-bold text-slate-850">{{ $selectedCurrencySymbol }}{{ number_format($gst_amount, 2) }}</td>
                                            </tr>
                                        @endif
                                        <tr class="border-t border-indigo-200 pt-2 font-black text-slate-900">
                                            <td class="py-3.5 text-left text-sm uppercase">Total Due:</td>
                                            <td class="py-3.5 text-right font-mono text-indigo-650 text-xl font-black">{{ $selectedCurrencySymbol }}{{ number_format($total_amount, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Toolbar -->
                    <div class="p-4 bg-slate-50/80 backdrop-blur rounded-2xl border border-slate-200/80 shadow-lg flex justify-end gap-3 no-print">
                        <button type="button" wire:click="resetInputFields" class="px-6 py-3 bg-white hover:bg-slate-50 text-slate-700 text-xs font-extrabold uppercase tracking-wider rounded-xl border border-slate-300 shadow-sm transition active:scale-95">
                            Cancel &amp; Close
                        </button>
                        <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-750 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl shadow-md transition active:scale-95 cursor-pointer">
                            {{ $isEditing ? 'Save & Update Purchase Order' : 'Approve & Create Purchase Order' }}
                        </button>
                    </div>
                </div>

                <!-- Right Side timeline drawer (Only on edit) -->
                @if($isEditing)
                <div class="lg:col-span-4 space-y-6 no-print">
                    <!-- Timeline Drawer Card -->
                    <div class="bg-white rounded-3xl p-6 shadow-xl border border-slate-200 space-y-6">
                        <div>
                            <h3 class="text-base font-black text-slate-800 tracking-tight">PO Action Log Timeline</h3>
                            <p class="text-xs text-slate-450">Track and manage real-time supplier lifecycle events.</p>
                        </div>

                        <!-- Supplier Quick Buttons -->
                        <div class="space-y-3">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Record Supplier Event:</span>
                            <div class="grid grid-cols-1 gap-2">
                                <button type="button" wire:click="logSupplierAction('sent_to_supplier', 'PO successfully dispatched to supplier email inbox.')" class="w-full py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-150 transition-all flex items-center justify-center gap-1">
                                    📨 Send to Supplier
                                </button>
                                <button type="button" wire:click="logSupplierAction('supplier_approve', 'Supplier has reviewed and officially approved PO parameters.')" class="w-full py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-150 transition-all flex items-center justify-center gap-1">
                                    ✓ Supplier Approve
                                </button>

                                <!-- Wants Changes form input -->
                                <div class="border border-slate-100 rounded-xl p-3 bg-slate-50/50 space-y-2">
                                    <textarea wire:model="supplierNotes" rows="1" class="w-full text-xs border border-slate-200 focus:border-indigo-500 focus:ring-0 rounded-lg p-1.5 bg-white" placeholder="Supplier changes request notes..."></textarea>
                                    <button type="button" wire:click="logSupplierAction('supplier_want_changes')" class="w-full py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold rounded-lg border border-amber-150 transition-all">
                                        ⚠️ Supplier Wants Changes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Render the Timeline List -->
                        @php
                            $poLogs = \App\Models\PurchaseOrderLog::with('user')->where('purchase_order_id', $orderId)->latest()->get();
                        @endphp
                        <div class="border-t border-slate-100 pt-4 space-y-4">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Lifecycle Timeline:</span>
                            
                            @if($poLogs->isEmpty())
                                <p class="text-xs text-slate-450 italic py-2">No timeline events recorded yet.</p>
                            @else
                                <div class="relative pl-4 border-l-2 border-slate-100 space-y-5 py-2">
                                    @foreach($poLogs as $log)
                                        <div class="relative">
                                            <!-- Dot badge icon -->
                                            <div class="absolute -left-[21px] top-0.5 w-2 h-2 rounded-full border border-white {{ $log->action === 'created' ? 'bg-slate-450' : ($log->action === 'finance_approved' ? 'bg-purple-500' : ($log->action === 'approved' || $log->action === 'supplier_approve' ? 'bg-emerald-500' : ($log->action === 'supplier_want_changes' ? 'bg-amber-500' : 'bg-indigo-500'))) }}"></div>
                                            
                                            <div class="flex items-center justify-between text-[10px] font-bold text-slate-700">
                                                <span class="capitalize tracking-tight">
                                                    {{ str_replace('_', ' ', $log->action) }}
                                                </span>
                                                <span class="text-[9px] text-slate-400 font-mono font-medium">{{ $log->created_at->diffForHumans() }}</span>
                                            </div>
                                            @if($log->notes)
                                                <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed font-semibold">{{ $log->notes }}</p>
                                            @endif
                                            <span class="block text-[8px] text-indigo-400 font-mono tracking-tight mt-0.5 font-medium">By: {{ $log->user->name ?? 'System' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </form>
    </div>
    @endif

    <!-- Bottom List Table -->
    @if($viewMode === 'table')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-3xl border border-slate-150">

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
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                            @if($order->approval_status === 'pending_approval')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                                    Pending L1
                                                </span>
                                            @elseif($order->approval_status === 'finance_approved')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                                    Finance Approved L1
                                                </span>
                                            @elseif($order->approval_status === 'approved')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Approved L2
                                                </span>
                                            @elseif($order->approval_status === 'rejected')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Rejected
                                                </span>
                                            @endif
                                        </div>

                                        @php
                                            $isSuperAdmin = auth()->user()->hasRole('Super Admin');
                                            $hasL1 = $isSuperAdmin || auth()->user()->can('approve_l1 purchase_orders');
                                            $hasL2 = $isSuperAdmin || auth()->user()->can('approve_l2 purchase_orders');
                                        @endphp
                                        @if(($hasL1 || $hasL2) && $order->approval_status !== 'approved')
                                            <div class="mt-1 flex gap-1.5 flex-wrap">
                                                @if(($order->approval_status === 'pending_approval' && $hasL1) || ($isSuperAdmin && $order->approval_status !== 'approved'))
                                                    <button type="button" wire:click="approveLevel1({{ $order->id }})" class="px-2 py-0.5 text-[10px] font-bold bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded transition-colors shadow-sm">
                                                        Approve Finance (L1)
                                                    </button>
                                                @endif
                                                @if(($order->approval_status === 'finance_approved' && $hasL2) || ($isSuperAdmin && $order->approval_status !== 'approved'))
                                                    <button type="button" wire:click="approveLevel2({{ $order->id }})" class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded transition-colors shadow-sm">
                                                        Approve Director (L2)
                                                    </button>
                                                @endif
                                                <button type="button" wire:click="rejectOrder({{ $order->id }})" class="px-2 py-0.5 text-[10px] font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded transition-colors shadow-sm">
                                                    Reject
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ \App\Models\Currency::where('code', $order->currency_code)->value('symbol') ?? setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <a href="{{ route('pdf.purchaseOrder', $order->id) }}" target="_blank" class="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors inline-flex items-center" title="PDF Preview & Download">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                    <button type="button" wire:click="edit({{ $order->id }})" class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-lg transition-colors inline-flex items-center" title="Edit Purchase Order">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button type="button" wire:click="delete({{ $order->id }})" wire:confirm="Are you sure?" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors inline-flex items-center" title="Delete Purchase Order">
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
        <!-- Purchase Order Kanban Pipeline View -->
        <div class="flex overflow-x-auto gap-6 pb-6 select-none scrollbar-thin" style="scrollbar-width: thin; -webkit-overflow-scrolling: touch;">
            @foreach([
                'draft' => ['name' => 'Draft PO', 'bg' => 'bg-slate-100/50', 'border' => 'border-slate-200', 'text' => 'text-slate-600'],
                'approved' => ['name' => 'Approved (L2) 🎉', 'bg' => 'bg-purple-50/30', 'border' => 'border-purple-100', 'text' => 'text-purple-600'],
                'shipped' => ['name' => 'Shipped 🚚', 'bg' => 'bg-blue-50/30', 'border' => 'border-blue-100', 'text' => 'text-blue-600'],
                'received' => ['name' => 'Received Goods ✓', 'bg' => 'bg-emerald-50/30', 'border' => 'border-emerald-100', 'text' => 'text-emerald-600'],
                'cancelled' => ['name' => 'Cancelled', 'bg' => 'bg-rose-50/20', 'border' => 'border-rose-100', 'text' => 'text-rose-600']
            ] as $statusKey => $statusVal)
                
                @php
                    $statusOrders = App\Models\PurchaseOrder::with(['supplier', 'items.product'])->where('status', $statusKey)->get();
                    $statusTotal = $statusOrders->sum('total_amount');
                @endphp

                <div x-data="{ draggingOver: false }"
                     x-on:dragenter.prevent="draggingOver = true"
                     x-on:dragleave.prevent="draggingOver = false"
                     x-on:dragover.prevent=""
                     x-on:drop="draggingOver = false; const poId = event.dataTransfer.getData('text/plain'); @this.movePoStatus(poId, '{{ $statusKey }}')"
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
                                    <div class="text-xs font-extrabold text-gray-900 leading-snug pr-4">PO-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</div>
                                    <a href="{{ route('pdf.purchaseOrder', $order->id) }}" target="_blank" onclick="event.stopPropagation()" class="p-1 text-indigo-600 hover:bg-indigo-50 rounded" title="PDF Preview">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                </div>
                                <div class="text-[10px] font-bold text-indigo-600 mt-0.5 truncate">{{ $order->supplier->name ?? 'N/A' }}</div>
                                
                                <!-- Items preview -->
                                <div class="mt-2 text-[9px] text-gray-400 border-t border-slate-100 pt-2 space-y-0.5 max-h-[60px] overflow-hidden font-medium">
                                    @foreach($order->items as $itm)
                                        <div class="truncate">{{ $itm->quantity }}x {{ $itm->product ? $itm->product->sku : 'Item' }}</div>
                                    @endforeach
                                </div>

                                <!-- Total Value -->
                                <div class="mt-3 flex items-baseline justify-between border-t border-slate-55 pt-2">
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">Total:</span>
                                    <span class="text-xs font-mono font-black text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</span>
                                </div>
                                
                                <div class="border-t border-gray-50 pt-2 mt-2.5 flex items-center justify-between text-[9px] font-bold" onclick="event.stopPropagation()">
                                    <span class="text-[8px] text-slate-400">Approval: {{ ucfirst($order->approval_status) }}</span>
                                    <select 
                                        onchange="event.stopPropagation(); @this.movePoStatus({{ $order->id }}, this.value)"
                                        onclick="event.stopPropagation()"
                                        class="p-0.5 text-[8px] bg-slate-50 border-gray-200 text-gray-600 rounded focus:ring-0 focus:border-indigo-600"
                                    >
                                        <option value="">Move...</option>
                                        @foreach(['draft' => 'Draft', 'approved' => 'Approved', 'shipped' => 'Shipped', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $k => $v)
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

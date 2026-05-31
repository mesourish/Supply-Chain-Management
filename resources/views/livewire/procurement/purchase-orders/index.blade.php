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
        ];
    }

    public function save()
    {
        $this->validate();

        $supplier = Supplier::findOrFail($this->supplier_id);
        if (!$supplier->contactPersons()->where('is_primary', true)->exists()) {
            $this->addError('supplier_id', 'This supplier must have a primary contact person before a Purchase Order can be created.');
            return;
        }

        PurchaseOrder::updateOrCreate(
            ['id' => $this->orderId],
            [
                'supplier_id' => $this->supplier_id,
                'status' => $this->status,
                'gst_type' => $this->gst_type,
                'gst_percentage' => $this->gst_percentage,
                'currency_code' => $this->currency_code,
                'exchange_rate' => \App\Models\Currency::where('code', $this->currency_code)->value('exchange_rate') ?? 1.0,
                'remarks' => $this->remarks,
                'terms_and_conditions' => $this->terms_and_conditions,
            ]
        );

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message:  $this->orderId ? 'PO Updated Successfully.' : 'PO Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $order = PurchaseOrder::findOrFail($id);
        $this->orderId = $id;
        $this->supplier_id = $order->supplier_id;
        $this->status = $order->status;
        $this->gst_type = $order->gst_type;
        $this->gst_percentage = $order->gst_percentage;
        $this->currency_code = $order->currency_code ?? 'USD';
        $this->exchange_rate = $order->exchange_rate ?? 1.0;
        $this->remarks = $order->remarks;
        $this->terms_and_conditions = $order->terms_and_conditions;
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
        $this->currency_code = 'USD';
        $this->exchange_rate = 1.0;
        $this->remarks = setting('default_po_remarks', '');
        $this->terms_and_conditions = setting('default_po_terms', '');
        $this->orderId = null;
        $this->isEditing = false;
    }

    public function mount()
    {
        $this->gst_type = setting('default_gst_type', 'exclusive');
        $this->gst_percentage = setting('default_gst_percentage', 0);
        $this->remarks = setting('default_po_remarks', '');
        $this->terms_and_conditions = setting('default_po_terms', '');
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
                                <td class="px-6 py-4 whitespace-nowrap">{{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}</td>
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

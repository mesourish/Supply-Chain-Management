<?php

use function Livewire\Volt\{state, mount, with, usesFileUploads};
use App\Models\AccountPayable;
use App\Models\Supplier;
use App\Models\PaymentLog;

usesFileUploads();

state([
    'suppliers' => [],
    'payableId' => null,
    'supplier_id' => '',
    'amount' => '',
    'status' => 'unpaid',
    'attachment' => null,
    'isEditing' => false,

    // Payments
    'paymentPayableId' => null,
    'payment_amount' => '',
    'payment_date' => '',
    'reference_number' => '',
    'notes' => '',
    'payment_attachment' => null,
]);

\Livewire\Volt\rules([
    'supplier_id' => 'required|exists:suppliers,id',
    'amount' => 'required|numeric|min:0',
    'status' => 'required|in:unpaid,partial,paid',
    'attachment' => 'nullable|file|max:10240',
]);

mount(function () {
    if (!auth()->user()->can('view dashboard')) { abort(403); }
    $this->suppliers = Supplier::orderBy('name')->get();
    $this->payment_date = date('Y-m-d');
});

with(fn () => [
    'payables' => AccountPayable::with(['purchaseOrder', 'supplier', 'payments'])->latest()->paginate(10),
]);

$save = function () {
    $this->validate();
    $path = null;
    if ($this->attachment && !is_string($this->attachment)) {
        $path = $this->attachment->store('payable_attachments', 'public');
    }

    $data = [
        'supplier_id' => $this->supplier_id,
        'amount' => $this->amount,
        'status' => $this->status,
    ];
    
    if ($path) {
        $data['attachment_path'] = $path;
    }

    AccountPayable::updateOrCreate(['id' => $this->payableId], $data);

    $this->resetInputFields();
    session()->flash('message', $this->payableId ? 'Payable Updated Successfully.' : 'Payable Created Successfully.');
};

$edit = function ($id) {
    $payable = AccountPayable::findOrFail($id);
    $this->payableId = $id;
    $this->supplier_id = $payable->supplier_id;
    $this->amount = $payable->amount;
    $this->status = $payable->status;
    $this->isEditing = true;
    $this->paymentPayableId = null;
};

$delete = function ($id) {
    AccountPayable::find($id)->delete();
    session()->flash('message', 'Payable Deleted Successfully.');
};

$resetInputFields = function () {
    $this->supplier_id = '';
    $this->amount = '';
    $this->status = 'unpaid';
    $this->attachment = null;
    $this->payableId = null;
    $this->isEditing = false;
};

$openPayment = function($id) {
    $this->paymentPayableId = $id;
    $this->payment_amount = '';
    $this->payment_date = date('Y-m-d');
    $this->reference_number = '';
    $this->notes = '';
    $this->payment_attachment = null;
    $this->resetInputFields(); // close edit form
};

$logPayment = function () {
    $this->validate([
        'payment_amount' => 'required|numeric|min:0.01',
        'payment_date' => 'required|date',
        'reference_number' => 'nullable|string',
        'notes' => 'nullable|string',
        'payment_attachment' => 'nullable|file|max:10240',
    ]);

    $path = null;
    if ($this->payment_attachment && !is_string($this->payment_attachment)) {
        $path = $this->payment_attachment->store('payment_attachments', 'public');
    }

    PaymentLog::create([
        'account_payable_id' => $this->paymentPayableId,
        'amount' => $this->payment_amount,
        'payment_date' => $this->payment_date,
        'reference_number' => $this->reference_number,
        'attachment_path' => $path,
        'notes' => $this->notes,
    ]);

    $payable = AccountPayable::find($this->paymentPayableId);
    if ($payable->balance <= 0) {
        $payable->update(['status' => 'paid']);
    } elseif ($payable->balance > 0 && $payable->amount_paid > 0) {
        $payable->update(['status' => 'partial']);
    }

    $this->paymentPayableId = null;
    session()->flash('message', 'Payment logged successfully.');
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <!-- Top Form (Supplier Style) -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Payable' : 'Create Manual Payable' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="supplier_id" value="Supplier *" />
                        <select wire:model="supplier_id" id="supplier_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select a supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Amount Due ({{ setting('currency_symbol', '$') }}) *" />
                        <x-text-input wire:model="amount" id="amount" type="number" step="0.01" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status *" />
                        <select wire:model="status" id="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="attachment" value="Attachment (Optional)" />
                        <input type="file" wire:model="attachment" id="attachment" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="attachment" class="text-xs text-indigo-600 mt-1">Uploading...</div>
                        <x-input-error :messages="$errors->get('attachment')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Payable' : 'Save Payable' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button type="button" wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Form Section (Appears when logging payment) -->
    @if($paymentPayableId)
    <div class="bg-indigo-50 border border-indigo-200 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-indigo-900">Record Payment for AP-#{{ $paymentPayableId }}</h2>
                <button type="button" wire:click="$set('paymentPayableId', null)" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i> Close</button>
            </div>
            
            @php
                $selectedAP = $payables->firstWhere('id', $paymentPayableId);
            @endphp

            @if($selectedAP)
            <div class="grid grid-cols-2 gap-4 mb-4 bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div>
                    <p class="text-sm text-gray-500">Total Amount</p>
                    <p class="text-lg font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($selectedAP->amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Balance Due</p>
                    <p class="text-lg font-bold text-red-600">{{ setting('currency_symbol', '$') }}{{ number_format($selectedAP->balance, 2) }}</p>
                </div>
            </div>
            @endif

            <form wire:submit.prevent="logPayment">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Payment Amount *" />
                        <x-text-input wire:model="payment_amount" type="number" step="0.01" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('payment_amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Payment Date *" />
                        <x-text-input wire:model="payment_date" type="date" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Reference Number" />
                        <x-text-input wire:model="reference_number" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label value="Payment Proof (Attachment)" />
                        <input type="file" wire:model="payment_attachment" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-100 file:text-indigo-700 hover:file:bg-indigo-200">
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label value="Notes" />
                        <textarea wire:model="notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <x-primary-button class="bg-indigo-600 hover:bg-indigo-700">Record Payment</x-primary-button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Payables List -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Accounts Payable Directory</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AP ID / PO Ref</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amounts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment History</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($payables as $ap)
                            <tr class="{{ $paymentPayableId == $ap->id ? 'bg-indigo-50' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">AP-#{{ $ap->id }}</div>
                                    @if($ap->purchase_order_id)
                                        <div class="text-sm text-gray-500">PO-#{{ $ap->purchase_order_id }}</div>
                                    @else
                                        <div class="text-xs text-gray-400">Manual</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $ap->supplier->name ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">Total: {{ setting('currency_symbol', '$') }}{{ number_format($ap->amount, 2) }}</div>
                                    <div class="text-sm font-bold text-red-600">Due: {{ setting('currency_symbol', '$') }}{{ number_format($ap->balance, 2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $ap->status === 'paid' ? 'bg-green-100 text-green-800' : ($ap->status === 'partial' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($ap->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($ap->payments->count() > 0)
                                        <div class="text-xs space-y-1">
                                        @foreach($ap->payments as $pmt)
                                            <div class="flex justify-between border-b border-gray-100 pb-1">
                                                <span>{{ $pmt->payment_date }}:</span>
                                                <span class="font-medium text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($pmt->amount, 2) }}</span>
                                            </div>
                                        @endforeach
                                        </div>
                                    @else
                                        <span class="text-gray-400 italic">No payments yet</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="openPayment({{ $ap->id }})" class="text-green-600 hover:text-green-900 mr-3" title="Record Payment">Pay</button>
                                    <button wire:click="edit({{ $ap->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @if($ap->attachment_path)
                                        <a href="{{ Storage::url($ap->attachment_path) }}" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3" title="View Attachment"><i class="fas fa-paperclip"></i></a>
                                    @endif
                                    <button wire:click="delete({{ $ap->id }})" wire:confirm="Are you sure you want to delete this payable?" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No payable records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $payables->links() }}
            </div>
            
        </div>
    </div>
</div>

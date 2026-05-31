<?php

use function Livewire\Volt\{state, mount, with, usesFileUploads};
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\PaymentLog;

usesFileUploads();

state([
    'customers' => [],
    'receivableId' => null,
    'customer_id' => '',
    'amount' => '',
    'status' => 'unpaid',
    'attachment' => null,
    'isEditing' => false,

    // Payments
    'paymentReceivableId' => null,
    'payment_amount' => '',
    'payment_date' => '',
    'reference_number' => '',
    'notes' => '',
    'payment_attachment' => null,
]);

\Livewire\Volt\rules([
    'customer_id' => 'required|exists:customers,id',
    'amount' => 'required|numeric|min:0',
    'status' => 'required|in:unpaid,partial,paid',
    'attachment' => 'nullable|file|max:10240',
]);

mount(function () {
    if (!auth()->user()->can('view dashboard')) { abort(403); }
    $this->customers = Customer::orderBy('name')->get();
    $this->payment_date = date('Y-m-d');
});

with(fn () => [
    'receivables' => AccountReceivable::with(['invoice', 'customer', 'payments'])->latest()->paginate(10),
]);

$save = function () {
    $this->validate();
    $path = null;
    if ($this->attachment && !is_string($this->attachment)) {
        $path = $this->attachment->store('receivable_attachments', 'public');
    }

    $data = [
        'customer_id' => $this->customer_id,
        'amount' => $this->amount,
        'status' => $this->status,
    ];
    
    if ($path) {
        $data['attachment_path'] = $path;
    }

    AccountReceivable::updateOrCreate(['id' => $this->receivableId], $data);

    $this->resetInputFields();
    $this->dispatch('toast', type: 'success', message:  $this->receivableId ? 'Receivable Updated Successfully.' : 'Receivable Created Successfully.');
};

$edit = function ($id) {
    $receivable = AccountReceivable::findOrFail($id);
    $this->receivableId = $id;
    $this->customer_id = $receivable->customer_id;
    $this->amount = $receivable->amount;
    $this->status = $receivable->status;
    $this->isEditing = true;
    $this->paymentReceivableId = null;
};

$delete = function ($id) {
    AccountReceivable::find($id)->delete();
    $this->dispatch('toast', type: 'success', message:  'Receivable Deleted Successfully.');
};

$resetInputFields = function () {
    $this->customer_id = '';
    $this->amount = '';
    $this->status = 'unpaid';
    $this->attachment = null;
    $this->receivableId = null;
    $this->isEditing = false;
};

$openPayment = function($id) {
    $this->paymentReceivableId = $id;
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
        'account_receivable_id' => $this->paymentReceivableId,
        'amount' => $this->payment_amount,
        'payment_date' => $this->payment_date,
        'reference_number' => $this->reference_number,
        'attachment_path' => $path,
        'notes' => $this->notes,
    ]);

    $receivable = AccountReceivable::find($this->paymentReceivableId);
    if ($receivable->balance <= 0) {
        $receivable->update(['status' => 'paid']);
    } elseif ($receivable->balance > 0 && $receivable->amount_paid > 0) {
        $receivable->update(['status' => 'partial']);
    }

    $this->paymentReceivableId = null;
    $this->dispatch('toast', type: 'success', message:  'Payment logged successfully.');
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <!-- Top Form (Supplier Style) -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Receivable' : 'Create Manual Receivable' }}</h2>

            

            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="customer_id" value="Customer *" />
                        <select wire:model="customer_id" id="customer_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select a customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
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
                    <x-primary-button>{{ $isEditing ? 'Update Receivable' : 'Save Receivable' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button type="button" wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Form Section (Appears when logging payment) -->
    @if($paymentReceivableId)
    <div class="bg-indigo-50 border border-indigo-200 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-indigo-900">Record Payment for AR-#{{ $paymentReceivableId }}</h2>
                <button type="button" wire:click="$set('paymentReceivableId', null)" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i> Close</button>
            </div>
            
            @php
                $selectedAR = $receivables->firstWhere('id', $paymentReceivableId);
            @endphp

            @if($selectedAR)
            <div class="grid grid-cols-2 gap-4 mb-4 bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div>
                    <p class="text-sm text-gray-500">Total Amount</p>
                    <p class="text-lg font-bold">{{ setting('currency_symbol', '$') }}{{ number_format($selectedAR->amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Balance Due</p>
                    <p class="text-lg font-bold text-red-600">{{ setting('currency_symbol', '$') }}{{ number_format($selectedAR->balance, 2) }}</p>
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

    <!-- Receivables List -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="flex items-center justify-between gap-4 mb-6">
                <h2 class="text-2xl font-semibold">Accounts Receivable Directory</h2>
                <a href="{{ url('/finance/payment-certificates') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-md transition-all duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Payment Certificates &rarr;
                </a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AR ID / Inv Ref</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amounts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment History</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($receivables as $ar)
                            <tr class="{{ $paymentReceivableId == $ar->id ? 'bg-indigo-50' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">AR-#{{ $ar->id }}</div>
                                    @if($ar->invoice_id)
                                        <div class="text-sm text-gray-500">INV-#{{ $ar->invoice_id }}</div>
                                    @else
                                        <div class="text-xs text-gray-400">Manual</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $ar->customer->name ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">Total: {{ setting('currency_symbol', '$') }}{{ number_format($ar->amount, 2) }}</div>
                                    <div class="text-sm font-bold text-red-600">Due: {{ setting('currency_symbol', '$') }}{{ number_format($ar->balance, 2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $ar->status === 'paid' ? 'bg-green-100 text-green-800' : ($ar->status === 'partial' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($ar->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($ar->payments->count() > 0)
                                        <div class="text-xs space-y-1">
                                        @foreach($ar->payments as $pmt)
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
                                    <button wire:click="openPayment({{ $ar->id }})" class="text-green-600 hover:text-green-900 mr-3" title="Record Payment">Receive</button>
                                    <button wire:click="edit({{ $ar->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @if($ar->attachment_path)
                                        <a href="{{ Storage::url($ar->attachment_path) }}" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3" title="View Attachment"><i class="fas fa-paperclip"></i></a>
                                    @endif
                                    <button wire:click="delete({{ $ar->id }})" wire:confirm="Are you sure you want to delete this receivable?" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No receivable records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $receivables->links() }}
            </div>
            
        </div>
    </div>
</div>

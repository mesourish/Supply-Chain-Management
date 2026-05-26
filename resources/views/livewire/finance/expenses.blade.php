<?php

use function Livewire\Volt\{state, mount, with, usesFileUploads};
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\Supplier;

usesFileUploads();

state([
    'purchaseOrders' => [],
    'suppliers' => [],
    'expenseId' => null,
    
    'purchase_order_id' => '',
    'supplier_id' => '',
    'category' => 'Miscellaneous',
    'amount' => '',
    'expense_date' => '',
    'reference_number' => '',
    'notes' => '',
    'attachment' => null,
    
    'isEditing' => false,
]);

\Livewire\Volt\rules([
    'purchase_order_id' => 'nullable|exists:purchase_orders,id',
    'supplier_id' => 'nullable|exists:suppliers,id',
    'category' => 'required|string|max:255',
    'amount' => 'required|numeric|min:0',
    'expense_date' => 'required|date',
    'reference_number' => 'nullable|string|max:255',
    'notes' => 'nullable|string',
    'attachment' => 'nullable|file|max:10240',
]);

mount(function () {
    if (!auth()->user()->can('view expenses')) { abort(403); }
    $this->purchaseOrders = PurchaseOrder::latest()->get();
    $this->suppliers = Supplier::orderBy('name')->get();
    $this->expense_date = date('Y-m-d');
});

with(fn () => [
    'expenses' => Expense::with(['purchaseOrder', 'supplier'])->latest()->paginate(10),
]);

$save = function () {
    if ($this->expenseId) {
        if (!auth()->user()->can('edit expenses')) abort(403);
    } else {
        if (!auth()->user()->can('create expenses')) abort(403);
    }
    $this->validate();
    $path = null;
    if ($this->attachment && !is_string($this->attachment)) {
        $path = $this->attachment->store('expense_attachments', 'public');
    }

    $data = [
        'purchase_order_id' => $this->purchase_order_id ?: null,
        'supplier_id' => $this->supplier_id ?: null,
        'category' => $this->category,
        'amount' => $this->amount,
        'expense_date' => $this->expense_date,
        'reference_number' => $this->reference_number,
        'notes' => $this->notes,
    ];
    
    if ($path) {
        $data['attachment_path'] = $path;
    }

    Expense::updateOrCreate(['id' => $this->expenseId], $data);

    $this->resetInputFields();
    session()->flash('message', $this->expenseId ? 'Expense Updated Successfully.' : 'Expense Logged Successfully.');
};

$edit = function ($id) {
    if (!auth()->user()->can('edit expenses')) abort(403);
    $expense = Expense::findOrFail($id);
    $this->expenseId = $id;
    $this->purchase_order_id = $expense->purchase_order_id ?? '';
    $this->supplier_id = $expense->supplier_id ?? '';
    $this->category = $expense->category;
    $this->amount = $expense->amount;
    $this->expense_date = $expense->expense_date;
    $this->reference_number = $expense->reference_number;
    $this->notes = $expense->notes;
    $this->isEditing = true;
};

$delete = function ($id) {
    if (!auth()->user()->can('delete expenses')) abort(403);
    Expense::find($id)->delete();
    session()->flash('message', 'Expense Deleted Successfully.');
};

$resetInputFields = function () {
    $this->expenseId = null;
    $this->purchase_order_id = '';
    $this->supplier_id = '';
    $this->category = 'Miscellaneous';
    $this->amount = '';
    $this->expense_date = date('Y-m-d');
    $this->reference_number = '';
    $this->notes = '';
    $this->attachment = null;
    $this->isEditing = false;
};
?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    @can('create expenses')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Expense' : 'Log New Expense' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="expense_date" value="Date *" />
                        <x-text-input wire:model="expense_date" id="expense_date" type="date" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('expense_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="category" value="Category *" />
                        <select wire:model="category" id="category" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            <option value="Miscellaneous">Miscellaneous</option>
                            <option value="Shipping & Logistics">Shipping & Logistics</option>
                            <option value="Customs & Duty">Customs & Duty</option>
                            <option value="Office Supplies">Office Supplies</option>
                            <option value="Travel">Travel</option>
                            <option value="Software/IT">Software/IT</option>
                        </select>
                        <x-input-error :messages="$errors->get('category')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Amount ({{ setting('currency_symbol', '$') }}) *" />
                        <x-text-input wire:model="amount" id="amount" type="number" step="0.01" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="supplier_id" value="Supplier (Optional)" />
                        <select wire:model="supplier_id" id="supplier_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">-- None --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="purchase_order_id" value="Purchase Order (Optional)" />
                        <select wire:model="purchase_order_id" id="purchase_order_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">-- None --</option>
                            @foreach($purchaseOrders as $po)
                                <option value="{{ $po->id }}">PO-#{{ $po->id }} ({{ $po->supplier->name ?? 'Unknown' }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('purchase_order_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="reference_number" value="Reference / Receipt #" />
                        <x-text-input wire:model="reference_number" id="reference_number" type="text" class="mt-1 block w-full" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea wire:model="notes" id="notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>
                    <div>
                        <x-input-label for="attachment" value="Attachment (Receipt/Invoice)" />
                        <input type="file" wire:model="attachment" id="attachment" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="attachment" class="text-xs text-indigo-600 mt-1">Uploading...</div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Expense' : 'Log Expense' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button type="button" wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>
    @endcan

    <!-- Expenses List -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Expenses Log</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Linked To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $expense->category }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    {{ setting('currency_symbol', '$') }}{{ number_format($expense->amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($expense->purchase_order_id)
                                        <div>PO-#{{ $expense->purchase_order_id }}</div>
                                    @endif
                                    @if($expense->supplier_id)
                                        <div>{{ $expense->supplier->name ?? 'Unknown Supplier' }}</div>
                                    @endif
                                    @if(!$expense->purchase_order_id && !$expense->supplier_id)
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $expense->notes }}">
                                    @if($expense->reference_number)
                                        <div class="font-medium text-gray-900">{{ $expense->reference_number }}</div>
                                    @endif
                                    <div class="text-xs">{{ $expense->notes }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if($expense->attachment_path)
                                        <a href="{{ Storage::url($expense->attachment_path) }}" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3" title="View Receipt"><i class="fas fa-paperclip"></i> View</a>
                                    @endif
                                    @can('edit expenses')
                                    <button wire:click="edit({{ $expense->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @endcan
                                    @can('delete expenses')
                                    <button wire:click="delete({{ $expense->id }})" wire:confirm="Are you sure you want to delete this expense?" class="text-red-600 hover:text-red-900">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No expenses logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $expenses->links() }}
            </div>
            
        </div>
    </div>
</div>

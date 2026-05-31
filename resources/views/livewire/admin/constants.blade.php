<?php

use App\Models\SystemConstant;
use Livewire\Volt\Component;

new class extends Component {
    public $type = 'product_category'; // Default tab
    
    // Form fields
    public $constantId;
    public $name;
    public $value;
    public $is_active = true;
    public $isEditing = false;

    public function mount()
    {
        if (!auth()->user()->can('view constants')) {
            abort(403);
        }
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'value' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function setType($type)
    {
        $this->type = $type;
        $this->resetForm();
    }

    public function getConstantsProperty()
    {
        return SystemConstant::where('type', $this->type)->orderBy('name')->get();
    }

    public function save()
    {
        if ($this->constantId && !auth()->user()->can('edit constants')) {
            abort(403);
        } elseif (!$this->constantId && !auth()->user()->can('create constants')) {
            abort(403);
        }

        $this->validate();

        SystemConstant::updateOrCreate(
            ['id' => $this->constantId],
            [
                'type' => $this->type,
                'name' => $this->name,
                'value' => $this->value,
                'is_active' => $this->is_active,
            ]
        );

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message:  'Constant saved successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('edit constants')) {
            abort(403);
        }

        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $constant = SystemConstant::findOrFail($id);
        $this->constantId = $constant->id;
        $this->name = $constant->name;
        $this->value = $constant->value;
        $this->is_active = $constant->is_active;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete constants')) {
            abort(403);
        }

        SystemConstant::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message:  'Constant deleted successfully.');
    }

    public function resetForm()
    {
        $this->constantId = null;
        $this->name = '';
        $this->value = '';
        $this->is_active = true;
        $this->isEditing = false;
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">System Constants Management</h2>
    </div>

    

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex">
                <button wire:click="setType('product_category')" class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'product_category' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Product Categories
                </button>
                <button wire:click="setType('expense_category')" class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'expense_category' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Expense Categories
                </button>
                <button wire:click="setType('gst_percentage')" class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'gst_percentage' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    GST Percentages
                </button>
            </nav>
        </div>

        <div class="p-6 text-gray-900">
            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $isEditing ? 'Edit' : 'Add New' }} {{ ucwords(str_replace('_', ' ', $type)) }}</h3>
            
            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="name" value="Name (Label)" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    
                    @if($type === 'gst_percentage')
                    <div>
                        <x-input-label for="value" value="Percentage Value (e.g., 5)" />
                        <x-text-input wire:model="value" id="value" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('value')" class="mt-2" />
                    </div>
                    @endif

                    <div class="flex items-center mt-6">
                        <label for="is_active" class="inline-flex items-center">
                            <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-600">Active</span>
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update' : 'Save' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetForm">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            @if($type === 'gst_percentage')
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($this->constants as $constant)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $constant->name }}</td>
                                @if($type === 'gst_percentage')
                                <td class="px-6 py-4 whitespace-nowrap">{{ $constant->value }}%</td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $constant->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $constant->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="edit({{ $constant->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $constant->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                        @if($this->constants->isEmpty())
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No {{ str_replace('_', ' ', $type) }} found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

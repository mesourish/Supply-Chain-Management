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

    // Currency fields
    public $currency_code;
    public $currency_name;
    public $currency_symbol;
    public $currency_exchange_rate = 1.0;
    public $currency_is_base = false;

    public function mount()
    {
        if (!auth()->user()->can('view constants')) {
            abort(403);
        }

        // Auto-seed USD if there are no currencies
        if (\App\Models\Currency::count() === 0) {
            \App\Models\Currency::create([
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate' => 1.0,
                'is_base' => true,
            ]);
        }
    }

    public function updatedCurrencyIsBase($value)
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->currency_exchange_rate = 1.0;
        }
    }

    public function rules()
    {
        if ($this->type === 'currency') {
            return [
                'currency_code' => 'required|string|size:3',
                'currency_name' => 'required|string|max:255',
                'currency_symbol' => 'required|string|max:10',
                'currency_exchange_rate' => 'required|numeric|min:0',
                'currency_is_base' => 'boolean',
            ];
        }
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
        if ($this->type === 'currency') {
            return \App\Models\Currency::orderBy('code')->get();
        }
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

        if ($this->type === 'currency') {
            $isBase = filter_var($this->currency_is_base, FILTER_VALIDATE_BOOLEAN);

            $currency = \App\Models\Currency::updateOrCreate(
                ['id' => $this->constantId],
                [
                    'code' => strtoupper($this->currency_code),
                    'name' => $this->currency_name,
                    'symbol' => $this->currency_symbol,
                    'exchange_rate' => $this->currency_exchange_rate,
                    'is_base' => $isBase,
                ]
            );

            if ($isBase) {
                // Reset all other currencies as base
                \App\Models\Currency::where('id', '!=', $currency->id)->update(['is_base' => false]);
                
                // Sync settings
                set_setting('default_currency_code', $currency->code);
                set_setting('currency_symbol', $currency->symbol);
            }
        } else {
            SystemConstant::updateOrCreate(
                ['id' => $this->constantId],
                [
                    'type' => $this->type,
                    'name' => $this->name,
                    'value' => $this->value,
                    'is_active' => $this->is_active,
                ]
            );
        }

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message:  'Constant saved successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('edit constants')) {
            abort(403);
        }

        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        
        if ($this->type === 'currency') {
            $currency = \App\Models\Currency::findOrFail($id);
            $this->constantId = $currency->id;
            $this->currency_code = $currency->code;
            $this->currency_name = $currency->name;
            $this->currency_symbol = $currency->symbol;
            $this->currency_exchange_rate = $currency->exchange_rate;
            $this->currency_is_base = (bool)$currency->is_base;
        } else {
            $constant = SystemConstant::findOrFail($id);
            $this->constantId = $constant->id;
            $this->name = $constant->name;
            $this->value = $constant->value;
            $this->is_active = $constant->is_active;
        }
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete constants')) {
            abort(403);
        }

        if ($this->type === 'currency') {
            \App\Models\Currency::findOrFail($id)->delete();
        } else {
            SystemConstant::findOrFail($id)->delete();
        }
        $this->dispatch('toast', type: 'success', message:  'Constant deleted successfully.');
    }

    public function resetForm()
    {
        $this->constantId = null;
        $this->name = '';
        $this->value = '';
        $this->is_active = true;
        $this->isEditing = false;

        $this->currency_code = '';
        $this->currency_name = '';
        $this->currency_symbol = '';
        $this->currency_exchange_rate = 1.0;
        $this->currency_is_base = false;
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">System Constants Management</h2>
    </div>

    

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex">
                <button wire:click="setType('product_category')" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'product_category' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Product Categories
                </button>
                <button wire:click="setType('expense_category')" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'expense_category' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Expense Categories
                </button>
                <button wire:click="setType('gst_percentage')" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'gst_percentage' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    GST Percentages
                </button>
                <button wire:click="setType('currency')" class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $type === 'currency' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Currencies
                </button>
            </nav>
        </div>

        <div class="p-6 text-gray-900">
            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $isEditing ? 'Edit' : 'Add New' }} {{ ucwords(str_replace('_', ' ', $type)) }}</h3>
            
            <form wire:submit="save">
                @if($type !== 'currency')
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
                @else
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="currency_code" value="Currency Code (e.g., USD)" />
                        <x-text-input wire:model="currency_code" id="currency_code" type="text" maxlength="3" class="mt-1 block w-full uppercase" required />
                        <x-input-error :messages="$errors->get('currency_code')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="currency_name" value="Currency Name (e.g., US Dollar)" />
                        <x-text-input wire:model="currency_name" id="currency_name" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('currency_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="currency_symbol" value="Currency Symbol (e.g., $)" />
                        <x-text-input wire:model="currency_symbol" id="currency_symbol" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('currency_symbol')" class="mt-2" />
                    </div>
                    <div>
                        @php
                            $baseCode = setting('default_currency_code', 'USD');
                        @endphp
                        <x-input-label for="currency_exchange_rate" value="Exchange Rate (relative to base {{ $baseCode }})" />
                        <x-text-input wire:model="currency_exchange_rate" id="currency_exchange_rate" type="number" step="0.000001" min="0" class="mt-1 block w-full {{ filter_var($currency_is_base, FILTER_VALIDATE_BOOLEAN) ? 'bg-gray-100 border-gray-200 text-gray-400' : '' }}" :disabled="filter_var($currency_is_base, FILTER_VALIDATE_BOOLEAN)" required />
                        <x-input-error :messages="$errors->get('currency_exchange_rate')" class="mt-2" />
                        <p class="text-[10px] text-gray-400 mt-1.5 font-semibold">e.g., if 1 {{ $baseCode }} = 0.92 EUR, enter 0.92 for EUR.</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center bg-indigo-50/50 p-4 rounded-xl border border-indigo-100/50">
                    <label for="currency_is_base" class="inline-flex items-center cursor-pointer">
                        <input wire:model.live="currency_is_base" id="currency_is_base" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-4 h-4">
                        <span class="ml-2.5 text-xs text-gray-700 font-extrabold uppercase tracking-wider">Is this the Base Currency?</span>
                    </label>
                    <span class="ml-4 text-[10px] text-slate-500 font-medium">— setting as base automatically locks rate to 1.0 and synchronizes General settings default base currency</span>
                </div>
                @endif

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
                            @if($type === 'currency')
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exchange Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Currency?</th>
                            @else
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            @if($type === 'gst_percentage')
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            @endif
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($this->constants as $constant)
                            <tr>
                                @if($type === 'currency')
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-gray-900">{{ $constant->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700">{{ $constant->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-indigo-700">{{ $constant->symbol }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-700">{{ number_format($constant->exchange_rate, 4) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($constant->is_base)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                            Yes (Base)
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-800">
                                            No
                                        </span>
                                    @endif
                                </td>
                                @else
                                <td class="px-6 py-4 whitespace-nowrap">{{ $constant->name }}</td>
                                @if($type === 'gst_percentage')
                                <td class="px-6 py-4 whitespace-nowrap">{{ $constant->value }}%</td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $constant->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $constant->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="edit({{ $constant->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $constant->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                        @if($this->constants->isEmpty())
                            <tr>
                                <td colspan="{{ $type === 'currency' ? 6 : 4 }}" class="px-6 py-4 text-center text-sm text-gray-500">No {{ str_replace('_', ' ', $type) }} found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

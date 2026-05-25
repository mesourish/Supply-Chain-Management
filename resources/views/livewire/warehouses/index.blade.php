<?php

use App\Models\Warehouse;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $name, $location, $contact_person_name, $contact_number, $contact_email, $address;
    public $is_active = true;
    public $isEditing = false;
    public $warehouseId = null;

    public function rules()
    {
        return [
            'name' => 'required|string|max:100',
            'location' => 'nullable|string',
            'contact_person_name' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'location' => $this->location,
            'contact_person_name' => $this->contact_person_name,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'address' => $this->address,
            'is_active' => $this->is_active,
        ];

        if (!$this->warehouseId) {
            $data['capacity'] = 0;
        }

        Warehouse::updateOrCreate(
            ['id' => $this->warehouseId],
            $data
        );

        $this->resetInputFields();
        session()->flash('message', $this->warehouseId ? 'Warehouse Updated Successfully.' : 'Warehouse Created Successfully.');
    }

    public function edit($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $this->warehouseId = $id;
        $this->name = $warehouse->name;
        $this->location = $warehouse->location;
        $this->contact_person_name = $warehouse->contact_person_name;
        $this->contact_number = $warehouse->contact_number;
        $this->contact_email = $warehouse->contact_email;
        $this->address = $warehouse->address;
        $this->is_active = $warehouse->is_active;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        Warehouse::find($id)->delete();
        session()->flash('message', 'Warehouse Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->name = '';
        $this->location = '';
        $this->contact_person_name = '';
        $this->contact_number = '';
        $this->contact_email = '';
        $this->address = '';
        $this->is_active = true;
        $this->warehouseId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        return [
            'warehouses' => Warehouse::withCount('bins')->latest()->paginate(10),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
            🏭 Warehouse Management
        </h1>
        <a href="{{ route('stock-take.index') }}"
           class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition shadow">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            Stock Take
        </a>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Warehouse' : 'Create Warehouse' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Warehouse Name *" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="location" value="Location Code" />
                        <x-text-input wire:model="location" id="location" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="contact_person_name" value="Contact Person" />
                        <x-text-input wire:model="contact_person_name" id="contact_person_name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('contact_person_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="contact_number" value="Phone" />
                        <x-text-input wire:model="contact_number" id="contact_number" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="contact_email" value="Email" />
                        <x-text-input wire:model="contact_email" id="contact_email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                    </div>
                    <div class="flex items-center mt-6">
                        <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Active Warehouse
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="address" value="Full Address" />
                        <textarea wire:model="address" id="address" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Warehouse' : 'Save Warehouse' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Warehouse Directory</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bins</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($warehouses as $warehouse)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $warehouse->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $warehouse->contact_person_name }}<br>
                                    {{ $warehouse->contact_number }}<br>
                                    {{ $warehouse->contact_email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $warehouse->location }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($warehouse->bins_count) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($warehouse->is_active)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('warehouses.show', ['id' => $warehouse->id]) }}" class="text-blue-600 hover:text-blue-900 mr-3">Manage</a>
                                    <button wire:click="edit({{ $warehouse->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $warehouse->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $warehouses->links() }}
            </div>
        </div>
    </div>
</div>

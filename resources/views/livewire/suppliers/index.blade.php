<?php

use App\Models\Supplier;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $name, $contact_person, $email, $phone, $tax_id, $address;
    public $is_active = true;
    public $isEditing = false;
    public $supplierId = null;

    public function rules()
    {
        return [
            'name' => 'required|string',
            'contact_person' => 'nullable|string',
            'email' => 'required|email|unique:suppliers,email,' . $this->supplierId,
            'phone' => 'nullable|string',
            'tax_id' => 'nullable|string',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function save()
    {
        $this->validate();

        $isNew = is_null($this->supplierId);

        $supplier = Supplier::updateOrCreate(
            ['id' => $this->supplierId],
            [
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'email' => $this->email,
                'phone' => $this->phone,
                'tax_id' => $this->tax_id,
                'address' => $this->address,
                'is_active' => $this->is_active,
            ]
        );

        if ($isNew) {
            // Auto-create default Address
            if (!empty($this->address)) {
                $supplier->addresses()->create([
                    'type' => 'billing',
                    'title' => 'Headquarters',
                    'address_line_1' => $this->address,
                    'is_default_billing' => true,
                    'is_default_shipping' => true,
                ]);
            }

            // Auto-create default Contact Person
            if (!empty($this->contact_person) || !empty($this->email) || !empty($this->phone)) {
                $supplier->contactPersons()->create([
                    'name' => $this->contact_person ?: 'Primary Contact',
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'is_primary' => true,
                    'designation' => 'Main Contact',
                ]);
            }
        } else {
            // If editing, try to update the primary contact/address if they exist
            $primaryContact = $supplier->contactPersons()->where('is_primary', true)->first();
            if ($primaryContact) {
                $primaryContact->update([
                    'name' => $this->contact_person ?: $primaryContact->name,
                    'email' => $this->email ?: $primaryContact->email,
                    'phone' => $this->phone ?: $primaryContact->phone,
                ]);
            }
        }

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message:  $this->supplierId ? 'Supplier Updated Successfully.' : 'Supplier Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $supplier = Supplier::findOrFail($id);
        $this->supplierId = $id;
        $this->name = $supplier->name;
        $this->contact_person = $supplier->contact_person;
        $this->email = $supplier->email;
        $this->phone = $supplier->phone;
        $this->tax_id = $supplier->tax_id;
        $this->address = $supplier->address;
        $this->is_active = $supplier->is_active;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        Supplier::find($id)->delete();
        $this->dispatch('toast', type: 'success', message:  'Supplier Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->name = '';
        $this->contact_person = '';
        $this->email = '';
        $this->phone = '';
        $this->tax_id = '';
        $this->address = '';
        $this->is_active = true;
        $this->supplierId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        return [
            'suppliers' => Supplier::latest()->paginate(10),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Supplier' : 'Create Supplier' }}</h2>

            

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Company Name *" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="contact_person" value="Contact Person" />
                        <x-text-input wire:model="contact_person" id="contact_person" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('contact_person')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email *" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input wire:model="phone" id="phone" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="tax_id" value="Tax ID / VAT No" />
                        <x-text-input wire:model="tax_id" id="tax_id" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                    </div>
                    <div class="flex items-center mt-6">
                        <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Active Supplier
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="address" value="Company Address" />
                        <textarea wire:model="address" id="address" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Supplier' : 'Save Supplier' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Supplier Directory</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($suppliers as $supplier)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $supplier->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $supplier->contact_person }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $supplier->email }}<br>
                                    {{ $supplier->phone }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $supplier->tax_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($supplier->is_active)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('suppliers.show', $supplier->id) }}" wire:navigate class="text-blue-600 hover:text-blue-900 mr-3">Manage</a>
                                    <button wire:click="edit({{ $supplier->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $supplier->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $suppliers->links() }}
            </div>
        </div>
    </div>
</div>

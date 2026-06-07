<?php

use App\Models\Customer;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $name, $contact_person, $email, $phone, $tax_id, $billing_address, $shipping_address;
    public $isEditing = false;
    public $customerId = null;

    public function rules()
    {
        return [
            'name' => 'required|string',
            'contact_person' => 'nullable|string',
            'email' => 'required|email|unique:customers,email,' . $this->customerId,
            'phone' => 'nullable|string',
            'tax_id' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
        ];
    }

    public function mount()
    {
        if (!auth()->user()->can('view customers')) abort(403);
    }

    public function save()
    {
        if ($this->customerId) {
            if (!auth()->user()->can('edit customers')) abort(403);
        } else {
            if (!auth()->user()->can('create customers')) abort(403);
        }
        $this->validate();

        Customer::updateOrCreate(
            ['id' => $this->customerId],
            [
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'email' => $this->email,
                'phone' => $this->phone,
                'tax_id' => $this->tax_id,
                'billing_address' => $this->billing_address,
                'shipping_address' => $this->shipping_address,
            ]
        );

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message:  $this->customerId ? 'Customer Updated Successfully.' : 'Customer Created Successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('edit customers')) abort(403);
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $customer = Customer::findOrFail($id);
        $this->customerId = $id;
        $this->name = $customer->name;
        $this->contact_person = $customer->contact_person;
        $this->email = $customer->email;
        $this->phone = $customer->phone;
        $this->tax_id = $customer->tax_id;
        $this->billing_address = $customer->billing_address;
        $this->shipping_address = $customer->shipping_address;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete customers')) abort(403);
        Customer::find($id)->delete();
        $this->dispatch('toast', type: 'success', message:  'Customer Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->name = '';
        $this->contact_person = '';
        $this->email = '';
        $this->phone = '';
        $this->tax_id = '';
        $this->billing_address = '';
        $this->shipping_address = '';
        $this->customerId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        return [
            'customers' => Customer::latest()->paginate(10),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Customer' : 'Create Customer' }}</h2>

            

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Customer/Company Name *" />
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
                    <div class="md:col-span-2">
                        <x-input-label for="tax_id" value="Tax ID / Resale Cert" />
                        <x-text-input wire:model="tax_id" id="tax_id" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="billing_address" value="Billing Address" />
                        <textarea wire:model="billing_address" id="billing_address" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('billing_address')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="shipping_address" value="Shipping Address" />
                        <textarea wire:model="shipping_address" id="shipping_address" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('shipping_address')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Customer' : 'Save Customer' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Customer Directory</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax ID</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($customers as $customer)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $customer->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $customer->contact_person }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $customer->email }}<br>
                                    {{ $customer->phone }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $customer->tax_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('customers.show', $customer->id) }}" class="text-blue-600 hover:text-blue-900 mr-3">Profile</a>
                                    <button wire:click="edit({{ $customer->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $customer->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        </div>
    </div>
</div>

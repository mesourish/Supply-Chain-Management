<?php

use App\Models\Supplier;
use App\Models\ContactPerson;
use App\Models\Address;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public Supplier $supplier;
    
    public $activeTab = 'contacts';
    
    // Contact Form
    public $showContactModal = false;
    public $contactId = null;
    public $contactName = '';
    public $contactEmail = '';
    public $contactPhone = '';
    public $contactDesignation = '';
    public $contactIsPrimary = false;

    // Address Form
    public $showAddressModal = false;
    public $addressId = null;
    public $addressType = 'billing';
    public $addressTitle = '';
    public $addressLine1 = '';
    public $addressLine2 = '';
    public $addressCity = '';
    public $addressState = '';
    public $addressZip = '';
    public $addressCountry = '';
    public $isDefaultBilling = false;
    public $isDefaultShipping = false;

    public function mount(Supplier $supplier)
    {
        $this->supplier = $supplier;
        $this->supplier->load(['contactPersons', 'addresses', 'products', 'purchaseOrders', 'accountPayables.payments', 'accountPayables.purchaseOrder']);
    }

    public function createContact()
    {
        $this->contactId = null;
        $this->contactName = '';
        $this->contactEmail = '';
        $this->contactPhone = '';
        $this->contactDesignation = '';
        $this->contactIsPrimary = false;
        $this->showContactModal = true;
    }

    public function saveContact()
    {
        $this->validate([
            'contactName' => 'required|string|max:255',
            'contactEmail' => 'nullable|email|max:255',
        ]);

        if ($this->contactIsPrimary) {
            $this->supplier->contactPersons()->update(['is_primary' => false]);
            $this->supplier->update([
                'contact_person' => $this->contactName,
                'email' => $this->contactEmail,
                'phone' => $this->contactPhone,
            ]);
        }

        $this->supplier->contactPersons()->updateOrCreate(
            ['id' => $this->contactId],
            [
                'name' => $this->contactName,
                'email' => $this->contactEmail,
                'phone' => $this->contactPhone,
                'designation' => $this->contactDesignation,
                'is_primary' => $this->contactIsPrimary,
            ]
        );

        $this->supplier->load('contactPersons');
        $this->showContactModal = false;
        $this->dispatch('toast', type: 'success', message: 'Contact saved successfully.');
    }

    public function deleteContact($id)
    {
        $this->supplier->contactPersons()->where('id', $id)->delete();
        $this->supplier->load('contactPersons');
        $this->dispatch('toast', type: 'success', message: 'Contact deleted.');
    }

    public function editContact($id)
    {
        $contact = $this->supplier->contactPersons()->where('id', $id)->first();
        if ($contact) {
            $this->contactId = $contact->id;
            $this->contactName = $contact->name;
            $this->contactEmail = $contact->email;
            $this->contactPhone = $contact->phone;
            $this->contactDesignation = $contact->designation;
            $this->contactIsPrimary = $contact->is_primary;
            $this->showContactModal = true;
        }
    }

    public function createAddress()
    {
        $this->addressId = null;
        $this->addressType = 'billing';
        $this->addressTitle = '';
        $this->addressLine1 = '';
        $this->addressLine2 = '';
        $this->addressCity = '';
        $this->addressState = '';
        $this->addressZip = '';
        $this->addressCountry = '';
        $this->isDefaultBilling = false;
        $this->isDefaultShipping = false;
        $this->showAddressModal = true;
    }

    public function saveAddress()
    {
        $this->validate([
            'addressTitle' => 'required|string|max:255',
            'addressLine1' => 'required|string|max:255',
        ]);

        if ($this->isDefaultBilling) {
            $this->supplier->addresses()->update(['is_default_billing' => false]);
        }
        if ($this->isDefaultShipping) {
            $this->supplier->addresses()->update(['is_default_shipping' => false]);
        }

        $this->supplier->addresses()->updateOrCreate(
            ['id' => $this->addressId],
            [
                'type' => $this->addressType,
                'title' => $this->addressTitle,
                'address_line_1' => $this->addressLine1,
                'address_line_2' => $this->addressLine2,
                'city' => $this->addressCity,
                'state' => $this->addressState,
                'postal_code' => $this->addressZip,
                'country' => $this->addressCountry,
                'is_default_billing' => $this->isDefaultBilling,
                'is_default_shipping' => $this->isDefaultShipping,
            ]
        );

        $this->supplier->load('addresses');
        $this->showAddressModal = false;
        $this->dispatch('toast', type: 'success', message: 'Address saved successfully.');
    }

    public function deleteAddress($id)
    {
        $this->supplier->addresses()->where('id', $id)->delete();
        $this->supplier->load('addresses');
        $this->dispatch('toast', type: 'success', message: 'Address deleted.');
    }

    public function editAddress($id)
    {
        $address = $this->supplier->addresses()->where('id', $id)->first();
        if ($address) {
            $this->addressId = $address->id;
            $this->addressType = $address->type;
            $this->addressTitle = $address->title;
            $this->addressLine1 = $address->address_line_1;
            $this->addressLine2 = $address->address_line_2;
            $this->addressCity = $address->city;
            $this->addressState = $address->state;
            $this->addressZip = $address->postal_code;
            $this->addressCountry = $address->country;
            $this->isDefaultBilling = $address->is_default_billing;
            $this->isDefaultShipping = $address->is_default_shipping;
            $this->showAddressModal = true;
        }
    }

    // Product Link Form
    public $showProductModal = false;
    public $isEditingProductLink = false;
    public $selectedProductId = '';
    public $productPrice = '';
    public $productSupplierSku = '';

    public function createProductLink()
    {
        $this->isEditingProductLink = false;
        $this->selectedProductId = '';
        $this->productPrice = '';
        $this->productSupplierSku = '';
        $this->showProductModal = true;
    }

    public function saveProductLink()
    {
        $this->validate([
            'selectedProductId' => 'required|exists:products,id',
            'productPrice' => 'required|numeric|min:0',
        ]);

        $this->supplier->products()->syncWithoutDetaching([
            $this->selectedProductId => [
                'price' => $this->productPrice,
                'supplier_sku' => $this->productSupplierSku,
            ]
        ]);

        $this->supplier->load('products');
        $this->showProductModal = false;
        $this->dispatch('toast', type: 'success', message: $this->isEditingProductLink ? 'Product link updated successfully.' : 'Product linked successfully.');
    }

    public function editProductLink($productId)
    {
        $product = $this->supplier->products()->where('product_id', $productId)->first();
        if ($product) {
            $this->isEditingProductLink = true;
            $this->selectedProductId = $product->id;
            $this->productPrice = $product->pivot->price;
            $this->productSupplierSku = $product->pivot->supplier_sku;
            $this->showProductModal = true;
        }
    }

    public function removeProductLink($productId)
    {
        $this->supplier->products()->detach($productId);
        $this->supplier->load('products');
        $this->dispatch('toast', type: 'success', message: 'Product unlinked.');
    }

    public function with()
    {
        $query = Product::where(function ($q) {
            $q->whereDoesntHave('suppliers', function($subQuery) {
                $subQuery->where('supplier_id', $this->supplier->id);
            });
            
            if ($this->isEditingProductLink && $this->selectedProductId) {
                $q->orWhere('id', $this->selectedProductId);
            }
        });

        return [
            'availableProducts' => $query->get()
        ];
    }
}; ?>
<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $supplier->name }}</h1>
            @if($supplier->contact_person)
                <p class="text-gray-700 text-sm mt-1"><span class="font-medium">Contact Person:</span> {{ $supplier->contact_person }}</p>
            @endif
            <p class="text-gray-500 text-sm mt-1">Manage Supplier Profile, Contacts, and Addresses</p>
        </div>
        <a href="{{ route('suppliers.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            Back to Suppliers
        </a>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6 overflow-x-auto">
        <nav class="-mb-px flex space-x-8">
            <button wire:click="$set('activeTab', 'contacts')" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'contacts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Contact Persons ({{ $supplier->contactPersons->count() }})
            </button>
            <button wire:click="$set('activeTab', 'addresses')" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'addresses' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Addresses ({{ $supplier->addresses->count() }})
            </button>
            <button wire:click="$set('activeTab', 'products')" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'products' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Linked Products ({{ $supplier->products->count() }})
            </button>
            <button wire:click="$set('activeTab', 'orders')" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'orders' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Purchase Orders ({{ $supplier->purchaseOrders->count() }})
            </button>
            <button wire:click="$set('activeTab', 'payables')" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'payables' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Account Payables ({{ $supplier->accountPayables->count() }})
            </button>
        </nav>
    </div>

    @if($activeTab === 'contacts')
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Contact Persons</h3>
            <button wire:click="createContact" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700">Add Contact</button>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email / Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Designation</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($supplier->contactPersons as $cp)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900">{{ $cp->name }} @if($cp->is_primary) <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Primary</span> @endif</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $cp->email }}<br>{{ $cp->phone }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cp->designation }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button wire:click="editContact({{ $cp->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                        <button wire:click="deleteContact({{ $cp->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:text-red-900">Delete</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($activeTab === 'addresses')
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Addresses</h3>
            <button wire:click="createAddress" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700">Add Address</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            @foreach($supplier->addresses as $addr)
            <div class="border rounded-lg p-4 relative">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="font-bold text-gray-900">{{ $addr->title }}</h4>
                    <div>
                        <button wire:click="editAddress({{ $addr->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm mr-3">Edit</button>
                        <button wire:click="deleteAddress({{ $addr->id }})" wire:confirm="Are you sure?" class="text-red-500 hover:text-red-700 text-sm">Delete</button>
                    </div>
                </div>
                <div class="mb-2">
                    @if($addr->is_default_billing) <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Default Billing</span> @endif
                    @if($addr->is_default_shipping) <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Default Shipping</span> @endif
                </div>
                <p class="text-sm text-gray-600">{{ $addr->address_line_1 }}</p>
                @if($addr->address_line_2)<p class="text-sm text-gray-600">{{ $addr->address_line_2 }}</p>@endif
                <p class="text-sm text-gray-600">{{ $addr->city }}, {{ $addr->state }} {{ $addr->postal_code }}</p>
                <p class="text-sm text-gray-600">{{ $addr->country }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($activeTab === 'products')
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Linked Products</h3>
            <button wire:click="createProductLink" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700">Link Product</button>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agreed Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($supplier->products as $prod)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900">{{ $prod->name }}</div>
                        <div class="text-sm text-gray-500">Internal SKU: {{ $prod->sku }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $prod->pivot->supplier_sku ?: 'N/A' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ setting('currency_symbol', '$') }}{{ number_format($prod->pivot->price, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button wire:click="editProductLink({{ $prod->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                        <button wire:click="removeProductLink({{ $prod->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:text-red-900">Unlink</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($activeTab === 'orders')
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Purchase Orders History</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($supplier->purchaseOrders as $order)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600">
                        <a href="{{ route('purchase-orders.show', $order->id) }}" wire:navigate>
                            {{ setting('sales_order_prefix', 'PO-') }}{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $order->created_at->format(setting('date_format', 'Y-m-d')) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            {{ $order->status === 'received' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ setting('currency_symbol', '$') }}{{ number_format($order->total_amount, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('purchase-orders.show', $order->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">View Details</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                        No purchase orders found for this supplier.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    @if($activeTab === 'payables')
    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Account Payables</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payable ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($supplier->accountPayables as $ap)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        AP-{{ str_pad($ap->id, 5, '0', STR_PAD_LEFT) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-600">
                        @if($ap->purchaseOrder)
                        <a href="{{ route('purchase-orders.show', $ap->purchase_order_id) }}" wire:navigate>
                            {{ setting('sales_order_prefix', 'PO-') }}{{ str_pad($ap->purchase_order_id, 5, '0', STR_PAD_LEFT) }}
                        </a>
                        @else
                        N/A
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            {{ $ap->status === 'paid' ? 'bg-green-100 text-green-800' : ($ap->status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($ap->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                        {{ setting('currency_symbol', '$') }}{{ number_format($ap->amount, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">
                        {{ setting('currency_symbol', '$') }}{{ number_format($ap->amount_paid, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right font-bold">
                        {{ setting('currency_symbol', '$') }}{{ number_format($ap->balance, 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                        No payables found for this supplier.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    <!-- Modals -->
    <x-modal wire:model="showContactModal" maxWidth="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ $contactId ? 'Edit Contact Person' : 'Add Contact Person' }}</h2>
            <form wire:submit.prevent="saveContact">
                <div class="space-y-4">
                    <div><x-input-label value="Name" /><x-text-input wire:model="contactName" class="w-full mt-1" required /></div>
                    <div><x-input-label value="Email" /><x-text-input type="email" wire:model="contactEmail" class="w-full mt-1" /></div>
                    <div><x-input-label value="Phone" /><x-text-input wire:model="contactPhone" class="w-full mt-1" /></div>
                    <div><x-input-label value="Designation" /><x-text-input wire:model="contactDesignation" class="w-full mt-1" /></div>
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="contactIsPrimary" id="is_primary" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <label for="is_primary" class="ml-2 text-sm text-gray-600">Make Primary Contact</label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button wire:click="$set('showContactModal', false)">Cancel</x-secondary-button>
                    <x-primary-button>Save Contact</x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>

    <x-modal wire:model="showAddressModal" maxWidth="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ $addressId ? 'Edit Address' : 'Add Address' }}</h2>
            <form wire:submit.prevent="saveAddress">
                <div class="space-y-4 grid grid-cols-2 gap-4">
                    <div class="col-span-2 mt-4"><x-input-label value="Title (e.g. HQ, Warehouse A)" /><x-text-input wire:model="addressTitle" class="w-full mt-1" required /></div>
                    <div class="col-span-2"><x-input-label value="Address Line 1" /><x-text-input wire:model="addressLine1" class="w-full mt-1" required /></div>
                    <div class="col-span-2"><x-input-label value="Address Line 2" /><x-text-input wire:model="addressLine2" class="w-full mt-1" /></div>
                    <div><x-input-label value="City" /><x-text-input wire:model="addressCity" class="w-full mt-1" /></div>
                    <div><x-input-label value="State/Province" /><x-text-input wire:model="addressState" class="w-full mt-1" /></div>
                    <div><x-input-label value="Postal Code" /><x-text-input wire:model="addressZip" class="w-full mt-1" /></div>
                    <div><x-input-label value="Country" /><x-text-input wire:model="addressCountry" class="w-full mt-1" /></div>
                    
                    <div class="col-span-2 flex items-center space-x-6">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="isDefaultBilling" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-600">Default Billing</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="isDefaultShipping" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-600">Default Shipping</span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button wire:click="$set('showAddressModal', false)">Cancel</x-secondary-button>
                    <x-primary-button>Save Address</x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>

    <x-modal wire:model="showProductModal" maxWidth="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ $isEditingProductLink ? 'Edit Linked Product' : 'Link Product to Supplier' }}</h2>
            <form wire:submit.prevent="saveProductLink">
                <div class="space-y-4">
                    <div>
                        <x-input-label value="Select Product *" />
                        <select wire:model="selectedProductId" class="w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required @if($isEditingProductLink) disabled @endif>
                            <option value="">-- Choose Product --</option>
                            @foreach($availableProducts as $ap)
                                <option value="{{ $ap->id }}">{{ $ap->name }} (SKU: {{ $ap->sku }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('selectedProductId')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Supplier SKU (Optional)" />
                        <x-text-input wire:model="productSupplierSku" class="w-full mt-1" placeholder="Their internal code" />
                    </div>
                    <div>
                        <x-input-label value="Agreed Purchase Price *" />
                        <x-text-input wire:model="productPrice" type="number" step="0.01" class="w-full mt-1" required />
                        <x-input-error :messages="$errors->get('productPrice')" class="mt-2" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button wire:click="$set('showProductModal', false)">Cancel</x-secondary-button>
                    <x-primary-button>Link Product</x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>
</div>

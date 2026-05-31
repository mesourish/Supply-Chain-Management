<?php

use App\Models\Product;
use App\Models\SystemConstant;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $sku, $barcode, $name, $category, $brand, $description, $unit_of_measure = 'pcs', $weight, $cost_price = 0, $unit_price = 0, $reorder_level = 10, $lead_time_days = 7, $velocity = 0;
    public $isEditing = false;
    public $productId = null;

    public function rules()
    {
        return [
            'sku' => 'required|string|unique:products,sku,' . $this->productId,
            'barcode' => 'nullable|string|unique:products,barcode,' . $this->productId,
            'name' => 'required|string',
            'category' => 'nullable|string',
            'brand' => 'nullable|string',
            'description' => 'nullable|string',
            'unit_of_measure' => 'required|string',
            'weight' => 'nullable|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'lead_time_days' => 'required|integer|min:1',
        ];
    }

    public function save()
    {
        $this->validate();

        Product::updateOrCreate(
            ['id' => $this->productId],
            [
                'sku' => $this->sku,
                'barcode' => $this->barcode,
                'name' => $this->name,
                'category' => $this->category,
                'brand' => $this->brand,
                'description' => $this->description,
                'unit_of_measure' => $this->unit_of_measure,
                'weight' => $this->weight,
                'cost_price' => $this->cost_price,
                'unit_price' => $this->unit_price,
                'reorder_level' => $this->reorder_level,
                'lead_time_days' => $this->lead_time_days ?? 7,
            ]
        );

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message:  $this->productId ? 'Product Updated Successfully.' : 'Product Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
        $product = Product::findOrFail($id);
        $this->productId = $id;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode;
        $this->name = $product->name;
        $this->category = $product->category;
        $this->brand = $product->brand;
        $this->description = $product->description;
        $this->unit_of_measure = $product->unit_of_measure;
        $this->weight = $product->weight;
        $this->cost_price = $product->cost_price;
        $this->unit_price = $product->unit_price;
        $this->reorder_level = $product->reorder_level;
        $this->lead_time_days = $product->lead_time_days ?? 7;
        $this->velocity = $product->velocity ?? 0;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        Product::find($id)->delete();
        $this->dispatch('toast', type: 'success', message:  'Product Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->sku = '';
        $this->barcode = '';
        $this->name = '';
        $this->category = '';
        $this->brand = '';
        $this->description = '';
        $this->unit_of_measure = 'pcs';
        $this->weight = '';
        $this->cost_price = 0;
        $this->unit_price = 0;
        $this->reorder_level = 10;
        $this->lead_time_days = 7;
        $this->velocity = 0;
        $this->productId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        return [
            'products' => Product::latest()->paginate(10),
            'categories' => SystemConstant::where('type', 'product_category')->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Product' : 'Create Product' }}</h2>

            

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <x-input-label for="sku" value="SKU *" />
                        <x-text-input wire:model="sku" id="sku" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="barcode" value="Barcode" />
                        <x-text-input wire:model="barcode" id="barcode" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="name" value="Product Name *" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    
                    <div>
                        <x-input-label for="category" value="Category" />
                        <select wire:model="category" id="category" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">-- Select Category --</option>
                            @if($category && !$categories->contains('name', $category))
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endif
                            @foreach($categories as $cat)
                                <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="brand" value="Brand" />
                        <x-text-input wire:model="brand" id="brand" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="unit_of_measure" value="Unit of Measure *" />
                        <x-text-input wire:model="unit_of_measure" id="unit_of_measure" type="text" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('unit_of_measure')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="weight" value="Weight" />
                        <x-text-input wire:model="weight" id="weight" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('weight')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="cost_price" value="Cost Price *" />
                        <x-text-input wire:model="cost_price" id="cost_price" type="number" step="0.01" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="unit_price" value="Selling Price *" />
                        <x-text-input wire:model="unit_price" id="unit_price" type="number" step="0.01" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('unit_price')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="reorder_level" value="Manual Reorder Level *" />
                        <x-text-input wire:model="reorder_level" id="reorder_level" type="number" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('reorder_level')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="lead_time_days" value="Lead Time (Days) *" />
                        <x-text-input wire:model="lead_time_days" id="lead_time_days" type="number" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('lead_time_days')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="description" value="Description" />
                        <textarea wire:model="description" id="description" rows="1" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Product' : 'Save Product' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Product List</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UOM / Weight</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prices</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AI Forecast</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($products as $product)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-12 w-12 shrink-0 mr-3">
                                            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(48)->generate($product->sku) !!}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $product->sku }}</div>
                                            <div class="text-xs text-gray-500">{{ $product->barcode }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $product->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $product->category }} &bull; {{ $product->brand }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->unit_of_measure }}<br>
                                    @if($product->weight) {{ $product->weight }} kg @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    Cost: {{ setting('currency_symbol', '$') }}{{ number_format($product->cost_price, 2) }}<br>
                                    Sell: {{ setting('currency_symbol', '$') }}{{ number_format($product->unit_price, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-[10px] text-gray-500 font-bold uppercase">Dynamic Reorder: <span class="text-indigo-600">{{ $product->dynamic_reorder_level ?? 'N/A' }}</span></div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">Velocity: {{ number_format($product->velocity, 1) }}/day</div>
                                    <div class="text-[10px] text-gray-400">Lead Time: {{ $product->lead_time_days }}d</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="edit({{ $product->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button wire:click="delete({{ $product->id }})" class="text-red-600 hover:text-red-900" wire:confirm="Are you sure?">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>

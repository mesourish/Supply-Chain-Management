<?php

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\WarehouseBin;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Form fields for Manual Adjustment
    public $product_id = '';
    public $from_bin_id = null;
    public $to_bin_id = null;
    public $type = 'transfer';
    public $quantity = 1;
    public $notes = '';

    public $products;
    public $bins;

    // Filters
    public $filter_product_id = '';
    public $filter_type = '';

    public function mount()
    {
        $this->products = Product::all();
        $this->bins = WarehouseBin::with('warehouse')->get();
    }

    public function rules()
    {
        return [
            'product_id' => 'required|exists:products,id',
            'from_bin_id' => 'nullable|exists:warehouse_bins,id',
            'to_bin_id' => 'nullable|exists:warehouse_bins,id',
            'type' => 'required|in:in,out,transfer,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ];
    }

    public function saveTransaction()
    {
        if (!auth()->user()->can('create inventory adjustments')) abort(403);
        $this->validate();

        // Basic validation depending on type
        if ($this->type === 'transfer' && (!$this->from_bin_id || !$this->to_bin_id)) {
            session()->flash('error', 'Both From and To Bins are required for transfers.');
            return;
        }

        InventoryTransaction::create([
            'product_id' => $this->product_id,
            'from_bin_id' => $this->from_bin_id ?: null,
            'to_bin_id' => $this->to_bin_id ?: null,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'reference_type' => 'Manual',
            'notes' => $this->notes,
            'user_id' => auth()->id(),
        ]);

        $this->resetInputFields();
        session()->flash('message', 'Inventory transaction recorded successfully.');
    }

    public function resetInputFields()
    {
        $this->product_id = '';
        $this->from_bin_id = null;
        $this->to_bin_id = null;
        $this->type = 'transfer';
        $this->quantity = 1;
        $this->notes = '';
    }

    public function with()
    {
        $query = InventoryTransaction::with(['product', 'fromBin.warehouse', 'toBin.warehouse', 'user'])
            ->latest();

        if ($this->filter_product_id) {
            $query->where('product_id', $this->filter_product_id);
        }
        if ($this->filter_type) {
            $query->where('type', $this->filter_type);
        }

        return [
            'transactions' => $query->paginate(15),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    
    @can('create inventory adjustments')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900 border-b border-gray-200 bg-gray-50">
            <h2 class="text-xl font-bold mb-4 text-indigo-700">Manual Inventory Adjustment / Transfer</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <form wire:submit="saveTransaction">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <x-input-label for="type" value="Transaction Type *" />
                        <select wire:model.live="type" id="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
                            <option value="transfer">Transfer (Bin to Bin)</option>
                            <option value="in">Adjustment In (+)</option>
                            <option value="out">Adjustment Out (-)</option>
                            <option value="adjustment">Stock Count Correction</option>
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="product_id" value="Product *" />
                        <select wire:model="product_id" id="product_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
                            <option value="">Select Product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quantity" value="Quantity *" />
                        <x-text-input wire:model="quantity" id="quantity" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>

                    @if($type === 'transfer' || $type === 'out' || $type === 'adjustment')
                    <div>
                        <x-input-label for="from_bin_id" value="From Bin" />
                        <select wire:model="from_bin_id" id="from_bin_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                            <option value="">Select Source Bin...</option>
                            @foreach($bins as $bin)
                                <option value="{{ $bin->id }}">{{ $bin->warehouse->name }} -> {{ $bin->full_label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('from_bin_id')" class="mt-2" />
                    </div>
                    @endif

                    @if($type === 'transfer' || $type === 'in' || $type === 'adjustment')
                    <div>
                        <x-input-label for="to_bin_id" value="To Bin" />
                        <select wire:model="to_bin_id" id="to_bin_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                            <option value="">Select Destination Bin...</option>
                            @foreach($bins as $bin)
                                <option value="{{ $bin->id }}">{{ $bin->warehouse->name }} -> {{ $bin->full_label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('to_bin_id')" class="mt-2" />
                    </div>
                    @endif

                    <div class="md:col-span-full">
                        <x-input-label for="notes" value="Notes / Reason" />
                        <x-text-input wire:model="notes" id="notes" type="text" class="mt-1 block w-full" placeholder="e.g. Damage write-off, initial stock, bin relocation..." />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>Record Transaction</x-primary-button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Inventory Movement Log</h2>
                
                <div class="flex space-x-2">
                    <select wire:model.live="filter_product_id" class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">All Products</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->sku }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filter_type" class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">All Types</option>
                        <option value="in">In</option>
                        <option value="out">Out</option>
                        <option value="transfer">Transfer</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Bin</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Bin</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference & Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($transactions as $txn)
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $txn->created_at->format('Y-m-d H:i') }}<br>
                                    <span class="text-xs text-gray-400">{{ $txn->user->name ?? 'System' }}</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $txn->product?->sku ?? 'N/A' }}<br>
                                    <span class="text-xs text-gray-500">{{ $txn->product?->name ?? 'Deleted Product' }}</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @php
                                        $color = match($txn->type) {
                                            'in' => 'bg-green-100 text-green-800',
                                            'out' => 'bg-red-100 text-red-800',
                                            'transfer' => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $color }}">
                                        {{ strtoupper($txn->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    {{ $txn->type === 'out' ? '-' : ($txn->type === 'in' ? '+' : '') }}{{ $txn->quantity }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($txn->fromBin)
                                        {{ $txn->fromBin->warehouse->name }} > {{ $txn->fromBin->full_label }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($txn->toBin)
                                        {{ $txn->toBin->warehouse->name }} > {{ $txn->toBin->full_label }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $txn->notes }}">
                                    @if($txn->reference_type)
                                        <span class="font-semibold text-indigo-600">{{ $txn->reference_type }} #{{ $txn->reference_id }}</span><br>
                                    @endif
                                    {{ $txn->notes }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No inventory transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</div>

<?php

use Livewire\Volt\Component;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\Product;
use App\Models\BinProductStock;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $showModal = false;

    public $warehouses = [];
    public $bins = [];
    public $products = [];

    // Form fields
    public $warehouse_id = '';
    public $bin_id = '';
    public $type = 'add';
    public $product_id = '';
    public $quantity = 1;
    public $notes = '';

    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) {
            abort(403);
        }
        $this->warehouses = Warehouse::orderBy('name')->get();
    }

    public function updatedWarehouseId($value)
    {
        $this->bin_id = '';
        $this->product_id = '';
        if ($value) {
            $this->bins = WarehouseBin::where('warehouse_id', $value)->orderBy('full_label')->get();
        } else {
            $this->bins = [];
        }
        $this->updateProductsList();
    }

    public function updatedBinId($value)
    {
        $this->product_id = '';
        $this->updateProductsList();
    }

    public function updatedType($value)
    {
        $this->product_id = '';
        $this->updateProductsList();
    }

    public function updateProductsList()
    {
        if ($this->type === 'add') {
            // Can add any product to the bin
            $this->products = Product::orderBy('name')->get();
        } else {
            // Can only remove products that exist in the bin
            if ($this->bin_id) {
                $this->products = Product::whereHas('binStocks', function ($query) {
                    $query->where('warehouse_bin_id', $this->bin_id);
                })->orderBy('name')->get();
            } else {
                $this->products = [];
            }
        }
    }

    public function createAdjustment()
    {
        $this->resetValidation();
        $this->warehouse_id = '';
        $this->bin_id = '';
        $this->type = 'add';
        $this->product_id = '';
        $this->quantity = 1;
        $this->notes = '';
        $this->bins = [];
        $this->products = Product::orderBy('name')->get(); // Default for 'add'
        
        $this->showModal = true;
    }

    public function saveAdjustment()
    {
        $this->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'bin_id'       => 'required|exists:warehouse_bins,id',
            'type'         => 'required|in:add,remove',
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|numeric|min:0.01',
            'notes'        => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $binStock = BinProductStock::where('warehouse_bin_id', $this->bin_id)
                ->where('product_id', $this->product_id)
                ->first();

            if ($this->type === 'remove') {
                if (!$binStock || $binStock->quantity < $this->quantity) {
                    $this->addError('quantity', 'Insufficient stock in this bin to remove the requested quantity.');
                    DB::rollBack();
                    return;
                }
                
                // Subtract stock
                $binStock->quantity -= $this->quantity;
                $binStock->save();

                // Log transaction
                InventoryTransaction::create([
                    'product_id' => $this->product_id,
                    'from_bin_id' => $this->bin_id,
                    'to_bin_id' => null,
                    'type' => 'adjustment_out',
                    'quantity' => $this->quantity,
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'notes' => $this->notes ?: 'Manual stock removal',
                    'user_id' => auth()->id(),
                ]);

            } else {
                // Add stock
                if ($binStock) {
                    $binStock->quantity += $this->quantity;
                    $binStock->save();
                } else {
                    $product = Product::find($this->product_id);
                    BinProductStock::create([
                        'warehouse_bin_id' => $this->bin_id,
                        'product_id' => $this->product_id,
                        'quantity' => $this->quantity,
                        'unit_cost' => $product->cost_price,
                    ]);
                }

                // Log transaction
                InventoryTransaction::create([
                    'product_id' => $this->product_id,
                    'from_bin_id' => null,
                    'to_bin_id' => $this->bin_id,
                    'type' => 'adjustment_in',
                    'quantity' => $this->quantity,
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'notes' => $this->notes ?: 'Manual stock addition',
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();
            $this->showModal = false;
            session()->flash('success', 'Stock adjustment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error recording adjustment: ' . $e->getMessage());
        }
    }

    public function with()
    {
        return [
            'transactions' => InventoryTransaction::with(['product', 'fromBin', 'toBin', 'user'])
                ->where('reference_type', 'manual')
                ->latest()
                ->paginate(15)
        ];
    }
};
?>

<div>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-semibold text-gray-900">Manual Stock Adjustments</h1>
            <p class="mt-2 text-sm text-gray-700">Manually add or remove stock from bins to reconcile inventory.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button wire:click="createAdjustment" class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto">
                + Create Adjustment
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mt-4 p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mt-4 p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <bg-gray-50 class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Date</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Product</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Qty</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Bin</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Notes</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">User</th>
                            </tr>
                        </bg-gray-50>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($transactions as $txn)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                        {{ $txn->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                        {{ $txn->product?->name ?? 'Unknown' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                        @if($txn->type === 'adjustment_in')
                                            <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Addition</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800">Removal</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                                        {{ $txn->type === 'adjustment_in' ? '+' : '-' }}{{ $txn->quantity }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $txn->type === 'adjustment_in' ? ($txn->toBin ? $txn->toBin->warehouse->name . ' > ' . $txn->toBin->full_label : '') : ($txn->fromBin ? $txn->fromBin->warehouse->name . ' > ' . $txn->fromBin->full_label : '') }}
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $txn->notes }}">
                                        {{ $txn->notes }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $txn->user?->name ?? 'System' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No manual adjustments found.</td>
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

    <!-- Create Adjustment Modal -->
    @if($showModal)
    <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

            <!-- Modal panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">New Stock Adjustment</h3>
                    
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Adjustment Type</label>
                            <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="add">Add Stock (+)</option>
                                <option value="remove">Remove Stock (-)</option>
                            </select>
                            @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                            <select wire:model.live="warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Select Warehouse --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        @if($warehouse_id)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Bin</label>
                            <select wire:model.live="bin_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Select Bin --</option>
                                @foreach($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->full_label }}</option>
                                @endforeach
                            </select>
                            @error('bin_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Product</label>
                            <select wire:model="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Select Product --</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                @endforeach
                            </select>
                            @if(count($products) === 0 && $bin_id && $type === 'remove')
                                <span class="text-xs text-yellow-600">No products found in this bin to remove.</span>
                            @endif
                            @error('product_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input type="number" wire:model="quantity" step="0.01" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes / Reason</label>
                            <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Reason for adjustment..."></textarea>
                            @error('notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
                    <button type="button" wire:click="saveAdjustment" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Submit
                    </button>
                    <button type="button" wire:click="$set('showModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

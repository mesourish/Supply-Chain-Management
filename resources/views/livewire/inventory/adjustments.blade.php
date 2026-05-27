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

    // Transfer specific fields
    public $to_bins = [];

    // Form fields
    public $warehouse_id = '';
    public $bin_id = '';
    public $type = 'add';
    public $product_id = '';
    public $quantity = 1;
    public $notes = '';

    // Destination for Transfer
    public $to_warehouse_id = '';
    public $to_bin_id = '';

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
            $this->bins = WarehouseBin::where('warehouse_id', $value)->orderBy('bin_code')->get();
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

    public function updatedToWarehouseId($value)
    {
        $this->to_bin_id = '';
        if ($value) {
            $this->to_bins = WarehouseBin::where('warehouse_id', $value)->orderBy('bin_code')->get();
        } else {
            $this->to_bins = [];
        }
    }

    public function updateProductsList()
    {
        if ($this->type === 'add') {
            // Can add any product to the bin
            $this->products = Product::orderBy('name')->get();
        } else {
            // Can only remove or transfer products that exist in the selected source bin
            if ($this->bin_id) {
                $this->products = Product::whereHas('binStocks', function ($query) {
                    $query->where('warehouse_bin_id', $this->bin_id)
                          ->where('quantity', '>', 0);
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
        $this->to_warehouse_id = '';
        $this->to_bin_id = '';
        $this->type = 'add';
        $this->product_id = '';
        $this->quantity = 1;
        $this->notes = '';
        $this->bins = [];
        $this->to_bins = [];
        $this->products = Product::orderBy('name')->get(); // Default for 'add'
        
        $this->showModal = true;
    }

    public function saveAdjustment()
    {
        if ($this->type === 'transfer') {
            $this->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'bin_id'       => 'required|exists:warehouse_bins,id',
                'to_warehouse_id' => 'required|exists:warehouses,id',
                'to_bin_id'    => 'required|exists:warehouse_bins,id|different:bin_id',
                'type'         => 'required|in:add,remove,transfer',
                'product_id'   => 'required|exists:products,id',
                'quantity'     => 'required|numeric|min:0.01',
                'notes'        => 'nullable|string|max:500',
            ], [
                'to_bin_id.different' => 'Destination bin must be different from the source bin.',
            ]);
        } else {
            $this->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'bin_id'       => 'required|exists:warehouse_bins,id',
                'type'         => 'required|in:add,remove,transfer',
                'product_id'   => 'required|exists:products,id',
                'quantity'     => 'required|numeric|min:0.01',
                'notes'        => 'nullable|string|max:500',
            ]);
        }

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

            } elseif ($this->type === 'transfer') {
                if (!$binStock || $binStock->quantity < $this->quantity) {
                    $this->addError('quantity', 'Insufficient stock in the source bin to transfer.');
                    DB::rollBack();
                    return;
                }

                // 1. Subtract from source
                $binStock->quantity -= $this->quantity;
                $binStock->save();

                // 2. Add to destination
                $destStock = BinProductStock::where('warehouse_bin_id', $this->to_bin_id)
                    ->where('product_id', $this->product_id)
                    ->first();

                if ($destStock) {
                    $destStock->quantity += $this->quantity;
                    $destStock->save();
                } else {
                    $product = Product::find($this->product_id);
                    BinProductStock::create([
                        'warehouse_bin_id' => $this->to_bin_id,
                        'product_id' => $this->product_id,
                        'quantity' => $this->quantity,
                        'unit_cost' => $product->cost_price,
                    ]);
                }

                // 3. Log single Transfer Transaction
                InventoryTransaction::create([
                    'product_id' => $this->product_id,
                    'from_bin_id' => $this->bin_id,
                    'to_bin_id' => $this->to_bin_id,
                    'type' => 'transfer',
                    'quantity' => $this->quantity,
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'notes' => $this->notes ?: 'Manual bin-to-bin stock transfer',
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

<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="sm:flex sm:items-center sm:justify-between mb-8">
            <div class="sm:flex-auto">
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Manual Stock Adjustments</h1>
                <p class="mt-2 text-sm text-gray-500">Perform real-time stock additions, removals, and physical bin-to-bin transfers.</p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button wire:click="createAdjustment" class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto transition-colors">
                    + Create Adjustment
                </button>
            </div>
        </div>

        @if (session()->has('success'))
            <div class="p-4 mb-6 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg">
                {{ session('success') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="p-4 mb-6 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <!-- Adjustments History Card -->
        <div class="bg-white shadow-sm border border-gray-100 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="py-4 pl-6 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-3 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                            <th scope="col" class="px-3 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Adjustment Type</th>
                            <th scope="col" class="px-3 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                            <th scope="col" class="px-3 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Bin Tracking</th>
                            <th scope="col" class="px-3 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reason / Memo</th>
                            <th scope="col" class="px-3 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Operator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($transactions as $txn)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-gray-500">
                                    {{ $txn->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-gray-900">
                                    {{ $txn->product?->sku ?? 'N/A' }} <span class="font-normal text-xs text-gray-400">({{ $txn->product?->name }})</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    @if($txn->type === 'adjustment_in')
                                        <span class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold border border-green-100 text-green-700">Addition</span>
                                    @elseif($txn->type === 'transfer')
                                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold border border-indigo-100 text-indigo-700">Bin Transfer</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold border border-rose-100 text-rose-700">Removal</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-gray-900">
                                    @php
                                        $prefix = $txn->type === 'adjustment_in' ? '+' : ($txn->type === 'transfer' ? '⇅' : '-');
                                        $color = $txn->type === 'adjustment_in' ? 'text-green-600' : ($txn->type === 'transfer' ? 'text-indigo-600' : 'text-rose-600');
                                    @endphp
                                    <span class="{{ $color }}">{{ $prefix }} {{ $txn->quantity }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    @if($txn->type === 'adjustment_in')
                                        <span class="text-xs text-gray-400">To:</span> <span class="font-medium text-gray-700">{{ $txn->toBin ? $txn->toBin->full_label : '-' }}</span>
                                    @elseif($txn->type === 'transfer')
                                        <span class="font-medium text-gray-700">{{ $txn->fromBin ? $txn->fromBin->full_label : '-' }}</span> ➔ <span class="font-medium text-gray-700">{{ $txn->toBin ? $txn->toBin->full_label : '-' }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">From:</span> <span class="font-medium text-gray-700">{{ $txn->fromBin ? $txn->fromBin->full_label : '-' }}</span>
                                    @endif
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
                                <td colspan="7" class="px-6 py-12 whitespace-nowrap text-sm text-gray-500 text-center">No manual adjustments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                    {{ $transactions->links() }}
                </div>
            @endif
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
                    <h3 class="text-lg leading-6 font-semibold text-gray-900 border-b border-gray-100 pb-3" id="modal-title">New Stock Adjustment</h3>
                    
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Adjustment Type *</label>
                            <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="add">Add Stock (+)</option>
                                <option value="remove">Remove Stock (-)</option>
                                <option value="transfer">Bin-to-Bin Transfer (⇅)</option>
                            </select>
                            @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ $type === 'transfer' ? 'Source Warehouse *' : 'Warehouse *' }}</label>
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
                                <label class="block text-sm font-medium text-gray-700">{{ $type === 'transfer' ? 'Source Bin *' : 'Bin *' }}</label>
                                <select wire:model.live="bin_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">-- Select Bin --</option>
                                    @foreach($bins as $bin)
                                        <option value="{{ $bin->id }}">{{ $bin->full_label }}</option>
                                    @endforeach
                                </select>
                                @error('bin_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            @endif
                        </div>

                        <!-- Destination for Transfer -->
                        @if($type === 'transfer')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-indigo-50/50 p-3 rounded-lg border border-indigo-100">
                            <div>
                                <label class="block text-sm font-medium text-indigo-900">Dest. Warehouse *</label>
                                <select wire:model.live="to_warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">-- Select Destination --</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @error('to_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            @if($to_warehouse_id)
                            <div>
                                <label class="block text-sm font-medium text-indigo-900">Dest. Bin *</label>
                                <select wire:model.live="to_bin_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">-- Select Bin --</option>
                                    @foreach($to_bins as $bin)
                                        <option value="{{ $bin->id }}">{{ $bin->full_label }}</option>
                                    @endforeach
                                </select>
                                @error('to_bin_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            @endif
                        </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Product *</label>
                            <select wire:model="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Select Product --</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                @endforeach
                            </select>
                            @if(count($products) === 0 && $bin_id && $type !== 'add')
                                <span class="text-xs text-yellow-600">No active stock in selected source bin to remove/transfer.</span>
                            @endif
                            @error('product_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Quantity *</label>
                                <input type="number" wire:model="quantity" step="0.01" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Reason / Memo *</label>
                                <input type="text" wire:model="notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g. Broken packaging, stock transfer...">
                                @error('notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 sm:mt-8 sm:flex sm:flex-row-reverse border-t border-gray-100 pt-4">
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

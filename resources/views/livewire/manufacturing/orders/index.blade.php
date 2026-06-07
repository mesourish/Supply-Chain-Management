<?php

use Livewire\Volt\Component;
use App\Models\ManufacturingOrder;
use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Models\WarehouseBin;
use App\Models\BinProductStock;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $showCreateModal = false;
    public $showCompleteModal = false;

    // Form fields
    public $product_id = '';
    public $bom_id = '';
    public $quantity_to_produce = 1.0000;
    public $scheduled_start_date = '';
    public $sales_order_id = null;

    // Complete fields
    public $selected_mo_id = null;
    public $selected_mo = null;
    public $target_bin_id = '';
    public $componentStatus = [];

    public function mount()
    {
        if (!Auth::user()->can('view manufacturing')) {
            abort(403);
        }
        $this->scheduled_start_date = now()->addDays(2)->toDateString();
    }

    public function updatedProductId($val)
    {
        // Auto select first BOM for product
        $firstBom = BillOfMaterial::where('product_id', $val)->first();
        $this->bom_id = $firstBom ? $firstBom->id : '';
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->scheduled_start_date = now()->addDays(2)->toDateString();
        $this->showCreateModal = true;
    }

    public function resetForm()
    {
        $this->product_id = '';
        $this->bom_id = '';
        $this->quantity_to_produce = 1.0000;
        $this->sales_order_id = null;
    }

    public function saveMO()
    {
        if (!Auth::user()->can('manage manufacturing')) {
            abort(403);
        }

        $this->validate([
            'product_id' => 'required|exists:products,id',
            'bom_id' => 'required|exists:bills_of_materials,id',
            'quantity_to_produce' => 'required|numeric|min:0.0001',
            'scheduled_start_date' => 'required|date',
        ]);

        $nextId = ManufacturingOrder::max('id') + 1;
        $moNumber = 'MO-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

        ManufacturingOrder::create([
            'mo_number' => $moNumber,
            'product_id' => $product_id = $this->product_id,
            'bom_id' => $this->bom_id,
            'quantity_to_produce' => $this->quantity_to_produce,
            'status' => 'draft',
            'scheduled_start_date' => $this->scheduled_start_date,
            'sales_order_id' => $this->sales_order_id,
        ]);

        $this->showCreateModal = false;
        $this->dispatch('toast', type: 'success', message: 'Manufacturing Order ' . $moNumber . ' created.');
    }

    public function updateStatus($id, $newStatus)
    {
        if (!Auth::user()->can('manage manufacturing')) {
            abort(403);
        }

        $mo = ManufacturingOrder::findOrFail($id);
        
        if ($newStatus === 'completed') {
            $this->selected_mo_id = $mo->id;
            $this->selected_mo = $mo;
            
            // Check stock of components
            $this->componentStatus = [];
            $bom = BillOfMaterial::with('items.product')->findOrFail($mo->bom_id);
            foreach ($bom->items as $item) {
                $qtyRequired = ($item->quantity_required * $mo->quantity_to_produce) / $bom->output_quantity;
                $currentStock = BinProductStock::where('product_id', $item->component_product_id)->sum('quantity');
                $this->componentStatus[] = [
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'required' => $qtyRequired,
                    'stock' => $currentStock,
                    'is_sufficient' => $currentStock >= $qtyRequired,
                ];
            }

            // Auto-select first bin
            $firstBin = WarehouseBin::first();
            $this->target_bin_id = $firstBin ? $firstBin->id : '';
            
            $this->showCompleteModal = true;
            return;
        }

        $mo->update(['status' => $newStatus]);
        $this->dispatch('toast', type: 'success', message: 'MO status updated to ' . $newStatus);
    }

    public function completeProduction()
    {
        if (!Auth::user()->can('manage manufacturing')) {
            abort(403);
        }

        $this->validate([
            'target_bin_id' => 'required|exists:warehouse_bins,id',
        ]);

        DB::transaction(function () {
            $mo = ManufacturingOrder::with(['billOfMaterial.items.product', 'product'])->findOrFail($this->selected_mo_id);
            $bom = $mo->billOfMaterial;

            // 1. Consume raw components from inventory
            foreach ($bom->items as $item) {
                $qtyRequired = ($item->quantity_required * $mo->quantity_to_produce) / $bom->output_quantity;

                // Find a bin with stock of this component
                $binStock = BinProductStock::where('product_id', $item->component_product_id)
                    ->where('quantity', '>', 0)
                    ->first();

                if ($binStock) {
                    $fromBinId = $binStock->warehouse_bin_id;
                    $binStock->decrement('quantity', $qtyRequired);
                } else {
                    $fromBinId = $this->target_bin_id; // Fallback to destination bin or first available bin
                    $binStock = BinProductStock::firstOrCreate(
                        ['warehouse_bin_id' => $fromBinId, 'product_id' => $item->component_product_id],
                        ['quantity' => 0, 'unit_cost' => $item->product->cost_price]
                    );
                    $binStock->decrement('quantity', $qtyRequired);
                }

                // Log Out transaction
                InventoryTransaction::create([
                    'product_id' => $item->component_product_id,
                    'from_bin_id' => $fromBinId,
                    'to_bin_id' => null,
                    'type' => 'out',
                    'quantity' => $qtyRequired,
                    'reference_type' => ManufacturingOrder::class,
                    'reference_id' => $mo->id,
                    'notes' => 'Consumed for Manufacturing Order ' . $mo->mo_number,
                    'user_id' => auth()->id(),
                ]);
            }

            // 2. Receive finished good product into target bin
            $binStock = BinProductStock::firstOrCreate(
                ['warehouse_bin_id' => $this->target_bin_id, 'product_id' => $mo->product_id],
                ['quantity' => 0, 'unit_cost' => $mo->product->cost_price]
            );
            $binStock->increment('quantity', $mo->quantity_to_produce);

            // Log In transaction
            InventoryTransaction::create([
                'product_id' => $mo->product_id,
                'from_bin_id' => null,
                'to_bin_id' => $this->target_bin_id,
                'type' => 'in',
                'quantity' => $mo->quantity_to_produce,
                'reference_type' => ManufacturingOrder::class,
                'reference_id' => $mo->id,
                'notes' => 'Produced by Manufacturing Order ' . $mo->mo_number,
                'user_id' => auth()->id(),
            ]);

            // 3. Complete order
            $mo->update([
                'status' => 'completed',
                'actual_completed_date' => now()->toDateString(),
            ]);
        });

        $this->showCompleteModal = false;
        $this->selected_mo_id = null;
        $this->selected_mo = null;
        $this->dispatch('toast', type: 'success', message: 'Production completed successfully. Stock balances updated.');
    }

    public function rendering($view)
    {
        $query = ManufacturingOrder::with(['product', 'billOfMaterial', 'salesOrder']);

        if (trim($this->search)) {
            $query->where(function($q) {
                $q->where('mo_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('product', function($pq) {
                      $pq->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Metrics counts
        $draftCount = ManufacturingOrder::where('status', 'draft')->count();
        $confirmedCount = ManufacturingOrder::where('status', 'confirmed')->count();
        $inProgressCount = ManufacturingOrder::where('status', 'in_progress')->count();
        $qcCount = ManufacturingOrder::where('status', 'quality_check')->count();
        $completedCount = ManufacturingOrder::where('status', 'completed')->count();

        // Get products with BOMs
        $bomProductIds = BillOfMaterial::pluck('product_id')->toArray();
        $manufacturedProducts = Product::whereIn('id', $bomProductIds)->orderBy('name')->get();

        return $view->with([
            'orders' => $query->latest()->paginate(10),
            'manufacturedProducts' => $manufacturedProducts,
            'boms' => BillOfMaterial::orderBy('name')->get(),
            'bins' => WarehouseBin::with('warehouse')->get(),
            'draftCount' => $draftCount,
            'confirmedCount' => $confirmedCount,
            'inProgressCount' => $inProgressCount,
            'qcCount' => $qcCount,
            'completedCount' => $completedCount,
        ]);
    }
}; ?>

<div class="p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Manufacturing Orders (MO)</h1>
                <p class="text-sm text-slate-500 mt-1">Track and execute shop floor operations, assembly scheduling, and routing controls.</p>
            </div>
            @can('manage manufacturing')
            <button wire:click="openCreateModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm shadow-md shadow-indigo-600/10 hover:shadow-indigo-600/25 transition">
                New Manufacturing Order
            </button>
            @endcan
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <button wire:click="$set('statusFilter', 'draft')" class="text-left bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-slate-400 hover:shadow-md transition">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Draft</span>
                <div class="text-xl font-extrabold text-slate-800 mt-0.5">{{ $draftCount }}</div>
            </button>
            <button wire:click="$set('statusFilter', 'confirmed')" class="text-left bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-500 hover:shadow-md transition">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Confirmed</span>
                <div class="text-xl font-extrabold text-slate-800 mt-0.5">{{ $confirmedCount }}</div>
            </button>
            <button wire:click="$set('statusFilter', 'in_progress')" class="text-left bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-amber-500 hover:shadow-md transition">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">In Progress</span>
                <div class="text-xl font-extrabold text-slate-800 mt-0.5">{{ $inProgressCount }}</div>
            </button>
            <button wire:click="$set('statusFilter', 'quality_check')" class="text-left bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-violet-500 hover:shadow-md transition">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">QC Stage</span>
                <div class="text-xl font-extrabold text-slate-800 mt-0.5">{{ $qcCount }}</div>
            </button>
            <button wire:click="$set('statusFilter', 'completed')" class="text-left bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-emerald-500 hover:shadow-md transition">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Completed</span>
                <div class="text-xl font-extrabold text-slate-800 mt-0.5">{{ $completedCount }}</div>
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search MO code, finished product SKU..." class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <select wire:model.live="statusFilter" class="w-full sm:w-44 px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="quality_check">Quality Check</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>

                @if($statusFilter || $search)
                    <button wire:click="$set('statusFilter', ''); $set('search', '');" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 rounded-xl transition">Clear Filters</button>
                @endif
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-55/60">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">MO Number</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Product (Finished Good)</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Recipe (BOM)</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Qty to Produce</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Scheduled Date</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Linked SO</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-600">Action Flow</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($orders as $order)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-bold text-slate-800">{{ $order->mo_number }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-700">{{ $order->product->name }}</div>
                                    <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $order->product->sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-500 font-semibold">{{ $order->billOfMaterial->name }}</td>
                                <td class="px-6 py-4 font-bold text-slate-600">{{ number_format($order->quantity_to_produce, 2) }}</td>
                                <td class="px-6 py-4 text-slate-500 font-medium">{{ $order->scheduled_start_date }}</td>
                                <td class="px-6 py-4">
                                    @if($order->sales_order_id)
                                        <a href="{{ url('/sales/orders/' . $order->sales_order_id) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-1 rounded-lg">
                                            SO-{{ str_pad($order->sales_order_id, 5, '0', STR_PAD_LEFT) }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400 font-bold">MTO-Stock</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($order->status === 'draft')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">Draft</span>
                                    @elseif($order->status === 'confirmed')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">Confirmed</span>
                                    @elseif($order->status === 'in_progress')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">In Progress</span>
                                    @elseif($order->status === 'quality_check')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">Quality Check</span>
                                    @elseif($order->status === 'completed')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Completed</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">Cancelled</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @can('manage manufacturing')
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($order->status === 'draft')
                                                <button wire:click="updateStatus({{ $order->id }}, 'confirmed')" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition">
                                                    Confirm
                                                </button>
                                            @elseif($order->status === 'confirmed')
                                                <button wire:click="updateStatus({{ $order->id }}, 'in_progress')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs transition">
                                                    Produce
                                                </button>
                                            @elseif($order->status === 'in_progress')
                                                <button wire:click="updateStatus({{ $order->id }}, 'quality_check')" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition">
                                                    Release QC
                                                </button>
                                            @elseif($order->status === 'quality_check')
                                                <button wire:click="updateStatus({{ $order->id }}, 'completed')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition">
                                                    Complete
                                                </button>
                                            @else
                                                <span class="text-xs text-slate-400 font-bold">Processed</span>
                                            @endif
                                            
                                            @if(in_array($order->status, ['draft', 'confirmed', 'in_progress']))
                                                <button wire:click="updateStatus({{ $order->id }}, 'cancelled')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl text-xs transition">
                                                    Cancel
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">Read-Only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500">No manufacturing orders defined.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100">
                {{ $orders->links() }}
            </div>
        </div>
    </div>

    <!-- Create MO Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showCreateModal', false)"></div>

                <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl relative z-50">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Schedule Manufacturing Order</h3>
                    <p class="text-xs text-slate-400 mb-4">Start finished product assembly routing.</p>
                    
                    <form wire:submit="saveMO" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Finished Product to Produce</label>
                            <select wire:model.live="product_id" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                                <option value="">Select Finished Product...</option>
                                @foreach($manufacturedProducts as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </select>
                            @error('product_id') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Bill of Materials Recipe</label>
                            <select wire:model="bom_id" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                                <option value="">Select BOM...</option>
                                @foreach($boms->where('product_id', $product_id) as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->bom_code }})</option>
                                @endforeach
                            </select>
                            @error('bom_id') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Quantity to Produce</label>
                            <input type="number" step="0.0001" wire:model="quantity_to_produce" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                            @error('quantity_to_produce') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Scheduled Start Date</label>
                            <input type="date" wire:model="scheduled_start_date" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                            @error('scheduled_start_date') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition">Schedule Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Complete MO Modal (Stock verification & Destination Bin Selection) -->
    @if($showCompleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showCompleteModal', false)"></div>

                <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl relative z-50">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Complete Production</h3>
                    <p class="text-xs text-slate-400 mb-4">Complete production of order <b>{{ $selected_mo->mo_number }}</b> for <b>{{ $selected_mo->product->name }}</b> (Qty: {{ number_format($selected_mo->quantity_to_produce, 2) }}).</p>
                    
                    <form wire:submit="completeProduction" class="space-y-6">
                        
                        <!-- Stock Status -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Component Stock Verification</label>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl overflow-hidden text-xs">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-100">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-semibold text-slate-600">Material SKU</th>
                                            <th class="px-4 py-2 text-left font-semibold text-slate-600">Material Name</th>
                                            <th class="px-4 py-2 text-right font-semibold text-slate-600">Required</th>
                                            <th class="px-4 py-2 text-right font-semibold text-slate-600">On Hand</th>
                                            <th class="px-4 py-2 text-center font-semibold text-slate-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        @foreach($componentStatus as $status)
                                            <tr>
                                                <td class="px-4 py-2 font-mono font-bold text-slate-700">{{ $status['sku'] }}</td>
                                                <td class="px-4 py-2 text-slate-600 font-semibold">{{ $status['name'] }}</td>
                                                <td class="px-4 py-2 text-right text-slate-700 font-bold">{{ number_format($status['required'], 2) }}</td>
                                                <td class="px-4 py-2 text-right font-bold {{ $status['is_sufficient'] ? 'text-slate-800' : 'text-rose-600' }}">{{ number_format($status['stock'], 2) }}</td>
                                                <td class="px-4 py-2 text-center">
                                                    @if($status['is_sufficient'])
                                                        <span class="inline-flex items-center gap-1 font-bold text-[10px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Sufficient</span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 font-bold text-[10px] text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">Shortage</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Target Bin -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Destination Warehouse Bin (Receipt finished goods)</label>
                            <select wire:model="target_bin_id" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                                <option value="">Select Destination Bin...</option>
                                @foreach($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->warehouse->name }} &rarr; {{ $bin->bin_code }} ({{ $bin->zone_code }})</option>
                                @endforeach
                            </select>
                            @error('target_bin_id') <span class="text-xs text-rose-600 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="$set('showCompleteModal', false)" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-600/20 transition">Confirm Production &amp; Receive Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

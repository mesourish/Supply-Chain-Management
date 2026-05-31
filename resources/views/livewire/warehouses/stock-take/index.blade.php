<?php

use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\BinProductStock;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // New stock take form
    public $showCreateModal = false;
    public $st_warehouse_id = '';
    public $st_scope = 'full';       // full | zone | product_category
    public $st_scope_filter = '';
    public $st_notes = '';

    // Viewing a stock take
    public $viewingStockTakeId = null;
    public $searchItem = '';
    public $filterStatus = '';      // pending|counted
    public $showVarianceOnly = false;

    // Inline count entry
    public $countInputs = [];       // [item_id => qty]

    public function openCreate()
    {
        $this->st_warehouse_id = Warehouse::first()?->id ?? '';
        $this->st_scope = 'full';
        $this->st_scope_filter = '';
        $this->st_notes = '';
        $this->showCreateModal = true;
    }

    public function createStockTake()
    {
        $this->validate([
            'st_warehouse_id' => 'required|exists:warehouses,id',
            'st_scope'        => 'required|in:full,zone,product_category',
        ]);

        $stockTake = StockTake::create([
            'reference_no' => 'ST-' . date('Ymd') . '-' . str_pad(StockTake::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT),
            'warehouse_id' => $this->st_warehouse_id,
            'created_by'   => auth()->id(),
            'status'       => 'draft',
            'scope'        => $this->st_scope,
            'scope_filter' => $this->st_scope_filter ?: null,
            'notes'        => $this->st_notes ?: null,
        ]);

        // Generate line items from current bin stock
        $query = BinProductStock::with(['bin', 'product'])
            ->where('quantity', '>=', 0)
            ->whereHas('bin', fn($q) => $q->where('warehouse_id', $this->st_warehouse_id)->where('is_active', true));

        if ($this->st_scope === 'zone' && $this->st_scope_filter) {
            $query->whereHas('bin', fn($q) => $q->where('zone', $this->st_scope_filter));
        }

        foreach ($query->get() as $entry) {
            StockTakeItem::create([
                'stock_take_id'    => $stockTake->id,
                'warehouse_bin_id' => $entry->warehouse_bin_id,
                'product_id'       => $entry->product_id,
                'system_quantity'  => $entry->quantity,
                'status'           => 'pending',
            ]);
        }

        $stockTake->update(['status' => 'in_progress', 'counted_at' => now()]);

        $this->showCreateModal = false;
        $this->viewingStockTakeId = $stockTake->id;
        session()->flash('success', "Stock Take {$stockTake->reference_no} created with {$query->count()} items.");
    }

    public function startStockTake($id)
    {
        $st = StockTake::findOrFail($id);
        $st->update(['status' => 'in_progress', 'counted_at' => now()]);
        $this->viewingStockTakeId = $id;
    }

    public function viewStockTake($id)
    {
        $this->viewingStockTakeId = $id;
        $this->countInputs = [];
    }

    public function backToList()
    {
        $this->viewingStockTakeId = null;
        $this->countInputs = [];
    }

    public function saveCount($itemId)
    {
        if (!isset($this->countInputs[$itemId]) || $this->countInputs[$itemId] === '') return;

        $item = StockTakeItem::findOrFail($itemId);
        $item->update([
            'counted_quantity' => (int) $this->countInputs[$itemId],
            'status'           => 'counted',
            'counted_by'       => auth()->id(),
        ]);
        unset($this->countInputs[$itemId]);
        session()->flash('success', 'Count saved.');
    }

    public function saveAllCounts()
    {
        foreach ($this->countInputs as $itemId => $qty) {
            if ($qty === '' || $qty === null) continue;
            $item = StockTakeItem::find($itemId);
            if ($item) {
                $item->update([
                    'counted_quantity' => (int) $qty,
                    'status'           => 'counted',
                    'counted_by'       => auth()->id(),
                ]);
            }
        }
        $this->countInputs = [];
        session()->flash('success', 'All counts saved.');
    }

    public function submitForApproval($id)
    {
        $st = StockTake::findOrFail($id);
        $uncounted = $st->items()->where('status', 'pending')->count();
        if ($uncounted > 0) {
            $this->dispatch('toast', type: 'error', message:  "{$uncounted} items still pending count. Count all items first.");
            return;
        }
        $st->update(['status' => 'pending_approval']);
        session()->flash('success', 'Stock take submitted for approval.');
    }

    public function approveStockTake($id)
    {
        $st = StockTake::with('items')->findOrFail($id);

        // Apply variances — adjust bin_product_stock to match counted quantities
        foreach ($st->items as $item) {
            if ($item->counted_quantity === null) continue;
            BinProductStock::updateOrCreate(
                ['warehouse_bin_id' => $item->warehouse_bin_id, 'product_id' => $item->product_id],
                ['quantity' => $item->counted_quantity]
            );
        }

        $st->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        session()->flash('success', 'Stock take approved. Inventory updated to reflect physical count.');
    }

    public function cancelStockTake($id)
    {
        StockTake::findOrFail($id)->update(['status' => 'cancelled']);
        $this->viewingStockTakeId = null;
        session()->flash('success', 'Stock take cancelled.');
    }

    public function with()
    {
        if ($this->viewingStockTakeId) {
            $stockTake = StockTake::with(['warehouse', 'creator', 'approver'])->findOrFail($this->viewingStockTakeId);

            $itemsQuery = StockTakeItem::with(['bin', 'product', 'counter'])
                ->where('stock_take_id', $this->viewingStockTakeId)
                ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
                ->when($this->showVarianceOnly, fn($q) => $q->whereNotNull('counted_quantity')
                    ->whereRaw('counted_quantity != system_quantity'))
                ->when($this->searchItem, fn($q) => $q->whereHas('product', fn($pq) =>
                    $pq->where('name', 'like', "%{$this->searchItem}%")
                       ->orWhere('sku', 'like', "%{$this->searchItem}%")))
                ->orderBy('status')
                ->orderByRaw('(SELECT zone FROM warehouse_bins WHERE id = warehouse_bin_id) ASC')
                ->orderByRaw('(SELECT bin_code FROM warehouse_bins WHERE id = warehouse_bin_id) ASC');

            $items = $itemsQuery->paginate(25);

            $totalItems    = StockTakeItem::where('stock_take_id', $this->viewingStockTakeId)->count();
            $countedItems  = StockTakeItem::where('stock_take_id', $this->viewingStockTakeId)->where('status', 'counted')->count();
            $varianceItems = StockTakeItem::where('stock_take_id', $this->viewingStockTakeId)
                ->whereNotNull('counted_quantity')
                ->whereRaw('counted_quantity != system_quantity')
                ->count();
            $totalVariance = StockTakeItem::where('stock_take_id', $this->viewingStockTakeId)
                ->whereNotNull('counted_quantity')
                ->selectRaw('SUM(counted_quantity - system_quantity) as variance')
                ->value('variance') ?? 0;

            $zones = WarehouseBin::where('warehouse_id', $stockTake->warehouse_id)->distinct('zone')->pluck('zone');
            $warehouses = Warehouse::all();
            return compact('stockTake', 'items', 'totalItems', 'countedItems', 'varianceItems', 'totalVariance', 'zones', 'warehouses');
        }

        $stockTakes = StockTake::with(['warehouse', 'creator'])
            ->withCount(['items', 'items as counted_items_count' => fn($q) => $q->where('status', 'counted')])
            ->latest()->paginate(15);

        $warehouses = Warehouse::orderBy('name')->get();
        $zones = collect();
        $stockTake = null;
        $items = collect();
        $totalItems = $countedItems = $varianceItems = $totalVariance = 0;
        return compact('stockTakes', 'warehouses', 'zones', 'stockTake', 'items', 'totalItems', 'countedItems', 'varianceItems', 'totalVariance');
    }
}; ?>

<div class="min-h-screen bg-gray-50">

    {{-- Flashes --}}
    @if(session()->has('success'))
        <div x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,4000)"
             class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-green-600 text-white px-5 py-3 rounded-xl shadow-xl text-sm">
            ✓ {{ session('success') }}
        </div>
    @endif
    

    {{-- Page header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-screen-xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if($viewingStockTakeId)
                    <button wire:click="backToList" class="text-gray-400 hover:text-indigo-600 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                @endif
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        {{ $viewingStockTakeId ? ($stockTake->reference_no ?? 'Stock Take') : 'Stock Take Reports' }}
                    </h1>
                    <p class="text-sm text-gray-500">Physical Inventory Count · Variance Analysis · Approval Workflow</p>
                </div>
            </div>
            @if(!$viewingStockTakeId)
                <button wire:click="openCreate"
                        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Stock Take
                </button>
            @endif
        </div>
    </div>

    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6">

    {{-- ════════ LIST VIEW ════════ --}}
    @if(!$viewingStockTakeId)
        <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-left">Reference</th>
                        <th class="px-5 py-3 text-left">Warehouse</th>
                        <th class="px-5 py-3 text-left">Scope</th>
                        <th class="px-5 py-3 text-center">Items</th>
                        <th class="px-5 py-3 text-center">Progress</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">Created By</th>
                        <th class="px-5 py-3 text-left">Date</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stockTakes as $st)
                        @php
                            $badge = $st->status_badge;
                            $badgeColors = [
                                'gray'   => 'bg-gray-100 text-gray-600',
                                'blue'   => 'bg-blue-100 text-blue-700',
                                'yellow' => 'bg-yellow-100 text-yellow-700',
                                'green'  => 'bg-green-100 text-green-700',
                                'red'    => 'bg-red-100 text-red-700',
                            ];
                            $progress = $st->items_count > 0 ? round(($st->counted_items_count / $st->items_count) * 100) : 0;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-indigo-600 font-semibold">{{ $st->reference_no }}</td>
                            <td class="px-5 py-3 font-medium">{{ $st->warehouse->name ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-500">
                                {{ ucfirst($st->scope) }}
                                @if($st->scope_filter) <span class="text-xs text-indigo-600">({{ $st->scope_filter }})</span> @endif
                            </td>
                            <td class="px-5 py-3 text-center">{{ $st->items_count }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-1.5 min-w-[60px]">
                                        <div class="h-1.5 rounded-full bg-indigo-500" style="width:{{ $progress }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $progress }}%</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $badgeColors[$badge['color']] }}">{{ $badge['label'] }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $st->creator->name ?? '-' }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">{{ $st->created_at->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex justify-center gap-1">
                                    <button wire:click="viewStockTake({{ $st->id }})"
                                            class="px-3 py-1 text-xs bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200">
                                        {{ in_array($st->status, ['draft', 'in_progress']) ? 'Count' : 'View' }}
                                    </button>
                                    @if($st->status === 'pending_approval')
                                        <button wire:click="approveStockTake({{ $st->id }})"
                                                class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">
                                            Approve
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-16 text-gray-400">
                            <p class="text-lg">📋 No stock takes yet</p>
                            <p class="text-sm">Click <strong>New Stock Take</strong> to start a physical inventory count.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-5 py-3 border-t">{{ $stockTakes->links() }}</div>
        </div>
    @endif

    {{-- ════════ DETAIL VIEW ════════ --}}
    @if($viewingStockTakeId && $stockTake)
        @php
            $badge = $stockTake->status_badge;
            $badgeColors = ['gray'=>'bg-gray-100 text-gray-600','blue'=>'bg-blue-100 text-blue-700','yellow'=>'bg-yellow-100 text-yellow-700','green'=>'bg-green-100 text-green-700','red'=>'bg-red-100 text-red-700'];
            $progress = $totalItems > 0 ? round(($countedItems / $totalItems) * 100) : 0;
        @endphp

        {{-- Info bar --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow p-5 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $badgeColors[$badge['color']] }}">{{ $badge['label'] }}</span>
                        <span class="text-sm text-gray-500">{{ $stockTake->warehouse->name ?? '-' }}</span>
                        <span class="text-sm text-gray-400">· {{ ucfirst($stockTake->scope) }} count</span>
                    </div>
                    <p class="text-xs text-gray-400">
                        Created by {{ $stockTake->creator->name ?? '-' }} · {{ $stockTake->created_at->format('d M Y H:i') }}
                        @if($stockTake->approver) · Approved by {{ $stockTake->approver->name }} @endif
                    </p>
                    @if($stockTake->notes) <p class="text-sm text-gray-600 italic">{{ $stockTake->notes }}</p> @endif
                </div>

                {{-- Action buttons --}}
                <div class="flex gap-2">
                    @if(in_array($stockTake->status, ['in_progress','draft']))
                        <button wire:click="saveAllCounts"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg">
                            Save All Counts
                        </button>
                        <button wire:click="submitForApproval({{ $stockTake->id }})"
                                class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm rounded-lg">
                            Submit for Approval
                        </button>
                    @endif
                    @if($stockTake->status === 'pending_approval')
                        <button wire:click="approveStockTake({{ $stockTake->id }})"
                                wire:confirm="Approve and apply all counted quantities to inventory?"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg">
                            ✓ Approve & Apply
                        </button>
                    @endif
                    @if(!in_array($stockTake->status, ['approved','cancelled']))
                        <button wire:click="cancelStockTake({{ $stockTake->id }})" wire:confirm="Cancel this stock take?"
                                class="px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 text-sm rounded-lg">
                            Cancel
                        </button>
                    @endif
                </div>
            </div>

            {{-- KPIs --}}
            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach([
                    ['Total Items', $totalItems, 'text-gray-700'],
                    ['Counted', $countedItems . ' / ' . $totalItems . ' (' . $progress . '%)', 'text-blue-700'],
                    ['Items with Variance', $varianceItems, 'text-orange-600'],
                    ['Net Variance (units)', ($totalVariance > 0 ? '+' : '') . number_format($totalVariance), $totalVariance == 0 ? 'text-green-600' : 'text-red-600'],
                ] as [$label, $val, $color])
                    <div class="bg-gray-50 rounded-lg p-3 border">
                        <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
                        <p class="text-xl font-bold {{ $color }}">{{ $val }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Progress bar --}}
            <div class="mt-3">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full bg-indigo-500 transition-all duration-500" style="width:{{ $progress }}%"></div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input wire:model.live.debounce.400ms="searchItem" placeholder="Search product…"
                   class="text-sm border border-gray-300 rounded-lg px-3 py-2 w-56 focus:ring-indigo-500">
            <select wire:model.live="filterStatus" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="counted">Counted</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" wire:model.live="showVarianceOnly" class="rounded">
                Variance only
            </label>
        </div>

        {{-- Items table --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Location</th>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-center">System Qty</th>
                        <th class="px-4 py-3 text-center">Counted Qty</th>
                        <th class="px-4 py-3 text-center">Variance</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        @if(in_array($stockTake->status, ['in_progress','draft']))
                            <th class="px-4 py-3 text-center">Enter Count</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $item)
                        @php
                            $variance = $item->counted_quantity !== null ? ($item->counted_quantity - $item->system_quantity) : null;
                        @endphp
                        <tr class="hover:bg-gray-50 {{ $variance !== null && $variance != 0 ? 'bg-orange-50 hover:bg-orange-50' : '' }}">
                            <td class="px-4 py-3">
                                <p class="font-mono text-xs font-bold text-indigo-700">{{ $item->bin->bin_code ?? '-' }}</p>
                                <p class="text-xs text-gray-400">{{ $item->bin->full_label ?? '' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $item->product->name ?? '-' }}</p>
                                <p class="text-xs text-gray-400">{{ $item->product->sku ?? '' }} · {{ $item->product->unit_of_measure ?? '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-gray-700">{{ number_format($item->system_quantity) }}</td>
                            <td class="px-4 py-3 text-center font-bold {{ $item->counted_quantity !== null ? 'text-gray-800' : 'text-gray-300' }}">
                                {{ $item->counted_quantity !== null ? number_format($item->counted_quantity) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-bold">
                                @if($variance !== null)
                                    <span class="{{ $variance > 0 ? 'text-green-600' : ($variance < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance) }}
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $item->status === 'counted' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            @if(in_array($stockTake->status, ['in_progress','draft']))
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <input type="number" min="0"
                                               wire:model.defer="countInputs.{{ $item->id }}"
                                               placeholder="{{ $item->counted_quantity ?? '' }}"
                                               class="w-20 text-sm text-center border border-gray-300 rounded-lg px-2 py-1 focus:ring-indigo-500">
                                        <button wire:click="saveCount({{ $item->id }})"
                                                class="px-3 py-1 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                                            Save
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-12 text-gray-400">No items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-5 py-3 border-t">{{ $items->links() }}</div>
        </div>
    @endif

    </div>

    {{-- ═══════ Modal: Create Stock Take ═══════ --}}
    @if($showCreateModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">New Stock Take</h2>
                <button wire:click="$set('showCreateModal', false)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Warehouse *</label>
                    <select wire:model="st_warehouse_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Scope</label>
                    <select wire:model.live="st_scope" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="full">Full Warehouse Count</option>
                        <option value="zone">By Zone</option>
                        <option value="product_category">By Product Category</option>
                    </select>
                </div>
                @if($st_scope === 'zone')
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Zone Letter (e.g. A, B, C)</label>
                        <input wire:model="st_scope_filter" placeholder="A" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                @endif
                @if($st_scope === 'product_category')
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Category Name</label>
                        <input wire:model="st_scope_filter" placeholder="Electronics" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                @endif
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Notes</label>
                    <textarea wire:model="st_notes" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="Optional note…"></textarea>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-700">
                    ⚠ Creating a stock take will snapshot current system quantities. You will then physically count and record actual quantities.
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                <button wire:click="createStockTake" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                    Create Stock Take
                </button>
            </div>
        </div>
    </div>
    @endif

</div>

<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectMaterialRequest;
use App\Models\Product;
use App\Models\WarehouseBin;
use App\Models\BinProductStock;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public Project $project;
    
    // Milestones Form Fields
    public $milestoneTitle = '';
    public $milestoneDesc = '';
    public $milestoneDue = '';
    
    // Material Requests / Reservations Form Fields
    public $productId = '';
    public $qtyRequested = 1;
    public $qtyReserved = 0;
    public $binId = '';
    public $reservationNotes = '';

    // Bins cache for selected product
    public $availableBinStocks = [];

    // UI state
    public $showMilestoneModal = false;
    public $showMaterialModal = false;

    public function mount(Project $project)
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->project = $project;
        $this->milestoneDue = date('Y-m-d');
        $this->loadProjectData();
    }

    public function loadProjectData()
    {
        $this->project->load([
            'milestones',
            'materialRequests.product',
            'materialRequests.bin.warehouse',
            'customer'
        ]);
    }

    public function resetMilestoneForm()
    {
        $this->milestoneTitle = '';
        $this->milestoneDesc = '';
        $this->milestoneDue = date('Y-m-d');
    }

    public function resetMaterialForm()
    {
        $this->productId = '';
        $this->qtyRequested = 1;
        $this->qtyReserved = 0;
        $this->binId = '';
        $this->reservationNotes = '';
        $this->availableBinStocks = [];
    }

    // Milestones management
    public function openMilestoneModal()
    {
        $this->resetMilestoneForm();
        $this->showMilestoneModal = true;
    }

    public function saveMilestone()
    {
        $this->validate([
            'milestoneTitle' => 'required|string|max:255',
            'milestoneDue' => 'required|date',
        ]);

        ProjectMilestone::create([
            'project_id' => $this->project->id,
            'title' => $this->milestoneTitle,
            'description' => $this->milestoneDesc,
            'due_date' => $this->milestoneDue,
            'status' => 'pending',
        ]);

        $this->showMilestoneModal = false;
        $this->loadProjectData();
        session()->flash('message', 'Milestone milestone added successfully.');
    }

    public function toggleMilestoneStatus($id)
    {
        $ms = ProjectMilestone::findOrFail($id);
        $newStatus = $ms->status === 'completed' ? 'pending' : 'completed';
        $ms->update(['status' => $newStatus]);
        
        $this->loadProjectData();
        session()->flash('message', "Milestone marked as {$newStatus}.");
    }

    // Material Request & Bin Stock Reservation (The core complex SCM logic)
    public function openMaterialModal()
    {
        $this->resetMaterialForm();
        $this->showMaterialModal = true;
    }

    public function updatedProductId($value)
    {
        $this->binId = '';
        $this->qtyReserved = 0;
        $this->availableBinStocks = [];

        if ($value) {
            // Find which physical bins contain this product, listing current quantity in stock
            $this->availableBinStocks = BinProductStock::where('product_id', $value)
                ->where('quantity', '>', 0)
                ->with(['bin.warehouse'])
                ->get()
                ->toArray();
        }
    }

    public function updatedBinId($value)
    {
        $this->qtyReserved = 0;
        if ($value && $this->productId) {
            $binStock = BinProductStock::where('warehouse_bin_id', $value)
                ->where('product_id', $this->productId)
                ->first();
            
            if ($binStock) {
                // Auto populate reservation quantity to fit either request or max available stock
                $this->qtyReserved = min($this->qtyRequested, $binStock->quantity);
            }
        }
    }

    public function reserveMaterial()
    {
        $this->validate([
            'productId' => 'required|exists:products,id',
            'qtyRequested' => 'required|numeric|min:0.01',
            'binId' => 'required|exists:warehouse_bins,id',
            'qtyReserved' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $binStock = BinProductStock::where('warehouse_bin_id', $this->binId)
                ->where('product_id', $this->productId)
                ->first();

            if (!$binStock || $binStock->quantity < $this->qtyReserved) {
                $this->addError('qtyReserved', 'Insufficient physical stock in this specific warehouse bin to complete reservation.');
                DB::rollBack();
                return;
            }

            // 1. Subtract physically from active bin stock (locks standard sales orders out)
            $binStock->quantity -= $this->qtyReserved;
            $binStock->save();

            // 2. Create material request reservation record
            ProjectMaterialRequest::create([
                'project_id' => $this->project->id,
                'product_id' => $this->productId,
                'warehouse_bin_id' => $this->binId,
                'quantity_requested' => $this->qtyRequested,
                'quantity_reserved' => $this->qtyReserved,
                'status' => 'reserved',
                'notes' => $this->reservationNotes ?: 'Stock allocation for ' . $this->project->code,
            ]);

            // 3. Log stock movement transaction
            InventoryTransaction::create([
                'product_id' => $this->productId,
                'from_bin_id' => $this->binId,
                'to_bin_id' => null,
                'type' => 'OUT',
                'quantity' => $this->qtyReserved,
                'reference_type' => 'project_reservation',
                'reference_id' => $this->project->id,
                'notes' => 'Reserved for Project ' . $this->project->code,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            $this->showMaterialModal = false;
            $this->loadProjectData();
            session()->flash('message', "Product stock successfully reserved and tagged to Project {$this->project->code}!");
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error reserving project stock: ' . $e->getMessage());
        }
    }
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">

    @if(session()->has('message'))
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm font-semibold text-sm">
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl shadow-sm font-semibold text-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header info -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-3.5 bg-indigo-600 text-white rounded-2xl shadow shadow-indigo-500/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-mono font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ $project->code }}</span>
                    <span class="text-xs font-black text-indigo-600 uppercase">{{ $project->status }}</span>
                </div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight mt-0.5">{{ $project->name }}</h1>
            </div>
        </div>
        <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-2 rounded-xl transition-colors">
            &larr; Back to Portfolios
        </a>
    </div>

    <!-- Overview Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Project Milestones & Progress -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Timeline & Milestones -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Project Milestones</h3>
                        <p class="text-xs text-gray-400">Track structural targets, execution delivery deadlines, and task completions.</p>
                    </div>
                    <button type="button" wire:click="openMilestoneModal" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors">
                        + Milestone
                    </button>
                </div>

                @php
                    $tMs = $project->milestones->count();
                    $cMs = $project->milestones->where('status', 'completed')->count();
                    $msProgress = $tMs > 0 ? ($cMs / $tMs) * 100 : 0;
                @endphp
                <div class="mb-6 space-y-2 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <div class="flex justify-between items-center text-xs font-bold text-gray-600">
                        <span>Milestone Progress Summary</span>
                        <span class="text-indigo-600">{{ $cMs }}/{{ $tMs }} completed ({{ number_format($msProgress, 0) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2.5">
                        <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $msProgress }}%"></div>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse($project->milestones as $ms)
                        <div class="flex items-start justify-between gap-4 p-3 rounded-2xl border {{ $ms->status === 'completed' ? 'bg-emerald-50/10 border-emerald-100' : 'bg-slate-50/30 border-slate-150' }} hover:shadow-sm transition-shadow">
                            <div class="flex items-start gap-3">
                                <input 
                                    type="checkbox" 
                                    {{ $ms->status === 'completed' ? 'checked' : '' }} 
                                    wire:click="toggleMilestoneStatus({{ $ms->id }})"
                                    class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mt-0.5"
                                >
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 {{ $ms->status === 'completed' ? 'line-through text-gray-400' : '' }}">{{ $ms->title }}</h4>
                                    @if($ms->description)
                                        <p class="text-xs text-gray-500 mt-1 leading-snug">{{ $ms->description }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="text-[10px] font-mono font-bold text-gray-400 whitespace-nowrap">Due: {{ date('M d, Y', strtotime($ms->due_date)) }}</span>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-400 text-sm italic">No milestones set. Click "+ Milestone" to initiate timeframes.</div>
                    @endforelse
                </div>
            </div>

            <!-- Material Reservations & Stock Allocation -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Target Inventory Stock Reservations</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Tag and allocate specific warehouse bin quantities directly to this project, bypassing general sales orders.</p>
                    </div>
                    <button type="button" wire:click="openMaterialModal" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors">
                        + Allocate Stock
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-150">
                        <thead>
                            <tr class="text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                <th class="px-4 py-3 rounded-l-xl">Material Product</th>
                                <th class="px-4 py-3">Allocated Bin</th>
                                <th class="px-4 py-3 text-center">Qty Requested</th>
                                <th class="px-4 py-3 text-center">Qty Reserved</th>
                                <th class="px-4 py-3 rounded-r-xl text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white text-sm">
                            @forelse($project->materialRequests as $req)
                                <tr class="hover:bg-gray-50/20 transition-colors">
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="font-extrabold text-gray-900">{{ $req->product->name ?? 'N/A' }}</div>
                                        <div class="text-[10px] text-gray-400 font-mono mt-0.5">SKU: {{ $req->product->sku ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if($req->bin)
                                            <div class="font-semibold text-gray-700">{{ $req->bin->warehouse->name }}</div>
                                            <div class="text-[10px] text-gray-400 mt-0.5 font-mono">Bin: {{ $req->bin->full_label }}</div>
                                        @else
                                            <span class="text-gray-400 italic">No bin allocated</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold text-gray-600">
                                        {{ $req->quantity_requested }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-extrabold text-indigo-600">
                                        {{ $req->quantity_reserved }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            {{ $req->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic text-xs">No active inventory materials reserved yet. Click "+ Allocate Stock" to prevent inventory hoarding.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right: Client Reference & Budgets -->
        <div class="space-y-6">
            <!-- Project Budget Info -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 space-y-4">
                <h3 class="text-sm font-bold text-gray-400 uppercase">Project Parameters</h3>
                
                <div class="space-y-4 border-b border-gray-100 pb-4">
                    <div>
                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Associated Customer Client</span>
                        <div class="font-extrabold text-gray-900 mt-0.5">{{ $project->customer->name ?? 'SCM Internal Project' }}</div>
                        @if($project->customer && $project->customer->company_name)
                            <div class="text-xs text-indigo-600 font-semibold mt-0.5">{{ $project->customer->company_name }}</div>
                        @endif
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Execution Timeline</span>
                        <div class="text-xs font-semibold text-gray-700 mt-0.5">
                            {{ date('M d, Y', strtotime($project->start_date)) }} &rarr; 
                            {{ $project->end_date ? date('M d, Y', strtotime($project->end_date)) : 'Ongoing' }}
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Allocated Financial Budget</span>
                    <h2 class="text-3xl font-black text-indigo-600 font-mono tracking-tight">${{ number_format($project->budget, 2) }}</h2>
                </div>
            </div>

            <!-- Description -->
            @if($project->description)
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 space-y-2 text-sm leading-relaxed">
                    <h3 class="text-sm font-bold text-gray-400 uppercase">Deliverable Scope</h3>
                    <div class="text-gray-600 font-medium whitespace-pre-line">{{ $project->description }}</div>
                </div>
            @endif
        </div>

    </div>

    <!-- Create Milestone Modal -->
    @if($showMilestoneModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showMilestoneModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Add Project Milestone</h3>

                    <form wire:submit.prevent="saveMilestone" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Milestone Title *" />
                            <x-text-input wire:model="milestoneTitle" type="text" class="mt-1 block w-full text-xs" required />
                            <x-input-error :messages="$errors->get('milestoneTitle')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Due Date *" />
                            <input type="date" wire:model="milestoneDue" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700" required>
                            <x-input-error :messages="$errors->get('milestoneDue')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Milestone / Target Deliverables" />
                            <textarea wire:model="milestoneDesc" rows="3" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Summarize tasks for this milestone..."></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showMilestoneModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Add Target</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Stock Allocation Modal (Target stock reservation) -->
    @if($showMaterialModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showMaterialModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Target Stock Reservator</h3>

                    <form wire:submit.prevent="reserveMaterial" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Target Product *" />
                            <select wire:model.live="productId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                <option value="">-- Choose Product --</option>
                                @foreach(Product::orderBy('name')->get() as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                @endforeach
                            </select>
                        </div>

                        @if($productId)
                            <div>
                                <x-input-label value="Quantity Required *" />
                                <input type="number" step="0.01" min="0.01" wire:model.live="qtyRequested" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                            </div>

                            <div>
                                <x-input-label value="Select Source Warehouse Bin (Live Stocks)*" />
                                <select wire:model.live="binId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-mono" required>
                                    <option value="">-- Select Active Stock Bin --</option>
                                    @foreach($availableBinStocks as $bst)
                                        <option value="{{ $bst['warehouse_bin_id'] }}">
                                            {{ $bst['bin']['warehouse']['name'] }} > {{ $bst['bin']['full_label'] }} (Available: {{ $bst['quantity'] }} units)
                                        </option>
                                    @endforeach
                                </select>
                                @if(count($availableBinStocks) === 0)
                                    <span class="text-xs text-rose-500 font-bold mt-1 block">🚨 Product is out of stock in all physical warehouses. Please create a supplier Purchase Order first.</span>
                                @endif
                            </div>

                            @if($binId)
                                <div>
                                    <x-input-label value="Target Stock Quantity to Reserve & Tag *" />
                                    <input type="number" step="0.01" min="0.01" wire:model.live="qtyReserved" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-extrabold text-indigo-600" required>
                                    <x-input-error :messages="$errors->get('qtyReserved')" class="mt-1" />
                                    <span class="text-[10px] text-gray-400 mt-1 block">This quantity will be physically subtracted from standard SCM stock list to tag exclusively for this project contract.</span>
                                </div>
                            @endif
                        @endif

                        <div>
                            <x-input-label value="Allocation Internal Notes" />
                            <textarea wire:model="reservationNotes" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Describe the stock tag reason..."></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showMaterialModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow" @if(count($availableBinStocks) === 0) disabled @endif>Reserve Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>

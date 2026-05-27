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

    // Invoices UI state
    public $showInvoiceModal = false;
    public $selectedInvoice = null;
    public $showEditInvoiceModal = false;
    public $editInvoiceId = null;
    public $editInvoiceAmount = 0;
    public $editInvoiceStatus = 'unpaid';
    public $editInvoiceProjectId = '';

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
            'customer',
            'expenses.supplier',
            'invoices.salesOrder'
        ]);
    }

    public function viewInvoice($id)
    {
        $this->selectedInvoice = \App\Models\Invoice::with(['salesOrder.customer', 'salesOrder.items.product', 'project'])->findOrFail($id);
        $this->showInvoiceModal = true;
    }

    public function editInvoice($id)
    {
        $invoice = \App\Models\Invoice::findOrFail($id);
        $this->editInvoiceId = $invoice->id;
        $this->editInvoiceAmount = $invoice->amount;
        $this->editInvoiceStatus = $invoice->status;
        $this->editInvoiceProjectId = $invoice->project_id;
        $this->showEditInvoiceModal = true;
    }

    public function updateInvoice()
    {
        $this->validate([
            'editInvoiceAmount' => 'required|numeric|min:0',
            'editInvoiceStatus' => 'required|string|in:issued,paid,unpaid',
        ]);

        $invoice = \App\Models\Invoice::findOrFail($this->editInvoiceId);
        $invoice->update([
            'amount' => $this->editInvoiceAmount,
            'status' => $this->editInvoiceStatus,
            'project_id' => $this->editInvoiceProjectId ?: null,
        ]);

        $this->showEditInvoiceModal = false;
        $this->loadProjectData();
        session()->flash('message', 'Invoice details updated successfully.');
    }

    public function changeInvoiceStatus($id)
    {
        $invoice = \App\Models\Invoice::findOrFail($id);
        $invoice->status = $invoice->status === 'paid' ? 'unpaid' : 'paid';
        $invoice->save();
        $this->loadProjectData();
        session()->flash('message', 'Invoice status toggled successfully.');
    }

    public function deleteInvoice($id)
    {
        $invoice = \App\Models\Invoice::findOrFail($id);
        $invoice->delete();
        $this->loadProjectData();
        session()->flash('message', 'Invoice deleted successfully.');
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

    <!-- Project Financial Control Console -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Project Financial Ledger</h3>
                <p class="text-xs text-gray-400">Chronological history of billed invoices and purchase expenses tagged to this project contract.</p>
            </div>
            <!-- Financial Highlights Row -->
            <div class="flex flex-wrap items-center gap-4 bg-slate-50 border border-slate-100 rounded-2xl p-3 select-none">
                <div class="text-center px-4 border-r border-slate-200">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400">Total Invoiced</span>
                    <h5 class="text-sm font-black text-indigo-600 mt-0.5 font-mono">${{ number_format($project->invoices->sum('amount'), 2) }}</h5>
                </div>
                <div class="text-center px-4 border-r border-slate-200">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400">Total Expenses</span>
                    <h5 class="text-sm font-black text-rose-600 mt-0.5 font-mono">${{ number_format($project->expenses->sum('amount'), 2) }}</h5>
                </div>
                <div class="text-center px-4">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400">Remaining Budget</span>
                    @php
                        $remaining = $project->budget - $project->expenses->sum('amount');
                    @endphp
                    <h5 class="text-sm font-black {{ $remaining >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-0.5 font-mono">${{ number_format($remaining, 2) }}</h5>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Project Invoices -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Project Invoices (Income)</h4>
                    <span class="text-[10px] font-bold font-mono text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ $project->invoices->count() }} invoices</span>
                </div>
                <div class="overflow-x-auto max-h-[300px] overflow-y-auto pr-1">
                    <table class="min-w-full divide-y divide-gray-100 text-xs">
                        <thead>
                            <tr class="text-left font-bold text-gray-400 bg-gray-50/50">
                                <th class="px-3 py-2 rounded-l-lg">Invoice ID</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 rounded-r-lg text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($project->invoices as $inv)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-3 py-3.5 whitespace-nowrap font-extrabold text-slate-900">
                                        <button type="button" wire:click="viewInvoice({{ $inv->id }})" class="hover:text-indigo-600 transition-colors font-extrabold text-left">INV-#{{ $inv->id }}</button>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-slate-500 font-mono">{{ $inv->created_at->format('M d, Y') }}</td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-right font-extrabold text-slate-900 font-mono">${{ number_format($inv->amount, 2) }}</td>
                                    <td class="px-3 py-3.5 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase {{ $inv->status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100' }}">
                                            {{ $inv->status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="viewInvoice({{ $inv->id }})" class="p-1 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors" title="View details and print">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                            <button type="button" wire:click="editInvoice({{ $inv->id }})" class="p-1 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-lg transition-colors" title="Edit invoice details">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                            <button type="button" wire:click="changeInvoiceStatus({{ $inv->id }})" class="p-1 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition-colors" title="Toggle paid/unpaid status">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                            </button>
                                            <button type="button" wire:click="deleteInvoice({{ $inv->id }})" onclick="confirm('Are you sure you want to permanently delete this billing sheet?') || event.stopImmediatePropagation()" class="p-1 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors" title="Delete invoice">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-gray-400 italic text-xs">No project invoices issued yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Project Expenses -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Project Expenses (Costs)</h4>
                    <span class="text-[10px] font-bold font-mono text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ $project->expenses->count() }} expenses</span>
                </div>
                <div class="overflow-x-auto max-h-[300px] overflow-y-auto pr-1">
                    <table class="min-w-full divide-y divide-gray-100 text-xs">
                        <thead>
                            <tr class="text-left font-bold text-gray-400 bg-gray-50/50">
                                <th class="px-3 py-2 rounded-l-lg">Reference</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($project->expenses as $exp)
                                <tr class="hover:bg-slate-50/50 transition-colors" title="{{ $exp->notes }}">
                                    <td class="px-3 py-3.5 whitespace-nowrap font-extrabold text-slate-900">
                                        <a href="{{ url('/finance/expenses') }}" class="hover:text-indigo-600 transition-colors">{{ $exp->reference_number ?: 'EXP-#' . $exp->id }}</a>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-slate-500 font-semibold">{{ $exp->category }}</td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-slate-500 font-mono">{{ date('M d, Y', strtotime($exp->expense_date)) }}</td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-right font-extrabold text-rose-600 font-mono">${{ number_format($exp->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-gray-400 italic text-xs">No project purchase expenses logged yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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

    <!-- Edit Invoice Modal -->
    @if($showEditInvoiceModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showEditInvoiceModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6 border border-gray-200 relative z-50">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Edit Invoice Details</h3>

                    <form wire:submit.prevent="updateInvoice" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Billing Amount ($) *" />
                            <x-text-input wire:model="editInvoiceAmount" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs" required />
                            <x-input-error :messages="$errors->get('editInvoiceAmount')" class="mt-1" />
                        </div>
                        
                        <div>
                            <x-input-label value="Settlement Status *" />
                            <select wire:model="editInvoiceStatus" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                <option value="unpaid">Unpaid / Open</option>
                                <option value="issued">Issued / Pending</option>
                                <option value="paid">Paid &amp; Settled</option>
                            </select>
                            <x-input-error :messages="$errors->get('editInvoiceStatus')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Associate with Project Contract" />
                            <select wire:model="editInvoiceProjectId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold">
                                <option value="">-- No Project Link (Internal) --</option>
                                @foreach(\App\Models\Project::orderBy('name')->get() as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->code }} - {{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showEditInvoiceModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Invoice Details & printable A4 Letterhead Modal -->
    @if($showInvoiceModal)
        <style>
            @media print {
                body {
                    background: white !important;
                    color: black !important;
                }
                body * {
                    visibility: hidden;
                }
                #printable-invoice-area, #printable-invoice-area * {
                    visibility: visible !important;
                }
                #printable-invoice-area {
                    position: fixed !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    z-index: 9999999 !important;
                    background: white !important;
                    color: black !important;
                    padding: 2cm !important;
                    margin: 0 !important;
                    box-shadow: none !important;
                    border: none !important;
                    visibility: visible !important;
                }
                .no-print {
                    display: none !important;
                }
            }
        </style>
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-60 no-print" wire:click="$set('showInvoiceModal', false)"></div>
                
                <div class="inline-block w-full max-w-3xl p-8 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl relative z-50 border border-slate-100">
                    
                    <!-- View Modal Header (No Print) -->
                    <div class="flex justify-between items-center border-b border-slate-100 pb-4 mb-6 no-print">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Invoice Ledger sheet</h3>
                            <p class="text-xs text-slate-400">View or download standard billing sheet.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print / PDF
                            </button>
                            <button wire:click="$set('showInvoiceModal', false)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    @if($selectedInvoice)
                        <!-- PRINT READY AREA (A4 Standard corporate letterhead layout) -->
                        <div id="printable-invoice-area" class="bg-white p-2 rounded-2xl select-text">
                            <!-- Corporate Letterhead Header -->
                            <div class="flex justify-between items-start border-b border-slate-150 pb-6 mb-8">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center text-white text-lg font-bold shadow-md">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </div>
                                        <span class="text-lg font-black text-slate-800 tracking-tight">SCM ERP System</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-2.5 max-w-xs leading-relaxed">
                                        Corporate Logistics, Warehousing &amp; Supply Chain Operations Hub.<br>
                                        Jebel Ali Free Zone, Dubai, United Arab Emirates
                                    </p>
                                </div>
                                <div class="text-right">
                                    <h2 class="text-3xl font-black text-indigo-600 font-mono uppercase tracking-tight">INVOICE</h2>
                                    <div class="text-xs text-slate-500 font-mono font-bold mt-1">INV-#{{ $selectedInvoice->id }}</div>
                                    <div class="text-[10px] text-slate-400 font-medium font-mono mt-0.5">Date: {{ $selectedInvoice->created_at->format('M d, Y') }}</div>
                                </div>
                            </div>

                            <!-- Billing Info -->
                            <div class="grid grid-cols-2 gap-8 mb-8 text-xs font-semibold">
                                <div class="space-y-1">
                                    <h4 class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Client Recipient</h4>
                                    <p class="text-sm font-black text-slate-850">{{ $selectedInvoice->salesOrder->customer->name ?? 'N/A' }}</p>
                                    @if($selectedInvoice->salesOrder && $selectedInvoice->salesOrder->customer && $selectedInvoice->salesOrder->customer->company_name)
                                        <p class="text-indigo-600 font-bold">{{ $selectedInvoice->salesOrder->customer->company_name }}</p>
                                    @endif
                                    <p class="text-slate-500 leading-relaxed font-normal">
                                        Email: {{ $selectedInvoice->salesOrder->customer->email ?? 'N/A' }}<br>
                                        Phone: {{ $selectedInvoice->salesOrder->customer->phone ?? 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-right space-y-1">
                                    <h4 class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Operational Tracking</h4>
                                    <p class="text-slate-700 font-bold">Sales Order: <span class="font-mono text-slate-900 font-extrabold">SO-#{{ $selectedInvoice->sales_order_id }}</span></p>
                                    <p class="text-slate-700 font-bold">Status: 
                                        <span class="font-black uppercase {{ $selectedInvoice->status === 'paid' ? 'text-emerald-600' : 'text-amber-600' }}">
                                            {{ $selectedInvoice->status }}
                                        </span>
                                    </p>
                                    @if($selectedInvoice->project)
                                        <p class="text-slate-700 font-bold">Project Tag: <span class="font-mono text-indigo-600 font-extrabold">{{ $selectedInvoice->project->code }}</span></p>
                                    @endif
                                </div>
                            </div>

                            <!-- Line items Table -->
                            <div class="mb-8">
                                <table class="min-w-full divide-y divide-slate-150 text-xs select-text">
                                    <thead>
                                        <tr class="text-left font-bold text-slate-400 bg-slate-50">
                                            <th class="px-4 py-3 rounded-l-xl">Line Item &amp; Product Specification</th>
                                            <th class="px-4 py-3 text-center">Quantity</th>
                                            <th class="px-4 py-3 text-right">Unit Price</th>
                                            <th class="px-4 py-3 rounded-r-xl text-right">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium">
                                        @if($selectedInvoice->salesOrder)
                                            @foreach($selectedInvoice->salesOrder->items as $item)
                                                <tr>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="font-black text-slate-800 text-xs">{{ $item->product->name }}</div>
                                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">SKU: {{ $item->product->sku }}</div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-bold text-slate-650">
                                                        {{ number_format($item->quantity, 0) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-mono text-slate-650">
                                                        ${{ number_format($item->unit_price, 2) }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-right font-black font-mono text-slate-800">
                                                        ${{ number_format($item->total_price, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Grand total sheet -->
                            <div class="flex justify-between items-start border-t border-slate-150 pt-6">
                                <div class="max-w-md">
                                    <h4 class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider">Payment Verification Terms</h4>
                                    <p class="text-[9.5px] text-slate-400 mt-1 max-w-sm leading-relaxed font-normal">
                                        This document represents a legally verified commercial billing invoice generated automatically by the SCM ERP general ledger. All shipments are subject to standard warehouse audits and delivery dispatch sheets.
                                    </p>
                                </div>
                                <div class="text-right w-64">
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1">
                                        <span>Subtotal:</span>
                                        <span class="font-mono text-slate-700">${{ number_format($selectedInvoice->amount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs font-bold text-slate-500 py-1 border-b border-slate-100">
                                        <span>Tax (0% VAT):</span>
                                        <span class="font-mono text-slate-700">$0.00</span>
                                    </div>
                                    <div class="flex justify-between text-base font-black text-slate-800 pt-3">
                                        <span class="text-slate-500 font-normal">Total Balance:</span>
                                        <span class="font-mono text-indigo-600 text-lg">${{ number_format($selectedInvoice->amount, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Signature and Verification hashes -->
                            <div class="grid grid-cols-2 gap-8 border-t border-slate-150 pt-10 mt-12 text-[9px] font-mono text-slate-400">
                                <div>
                                    <p class="font-bold text-slate-500 uppercase tracking-wider mb-8">Authorized Seal &amp; Audit Approval</p>
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-500 uppercase block">ERP Verification Token</span>
                                            <span class="text-[8px] font-mono tracking-tight">{{ hash('sha256', $selectedInvoice->id . '-' . $selectedInvoice->amount . '-' . $selectedInvoice->created_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex flex-col justify-end">
                                    <div class="border-b border-slate-200 w-48 ml-auto mb-1"></div>
                                    <span class="font-bold text-slate-500 uppercase block mr-4">Financial Ledger Officer</span>
                                    <span class="text-[8.5px] block mr-4">SCM ERP Operations Core</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>

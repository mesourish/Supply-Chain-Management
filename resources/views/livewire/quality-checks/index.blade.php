<?php

use function Livewire\Volt\{state, mount, with};
use App\Models\QualityCheck;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

state([
    'search' => '',
    'statusFilter' => '',
    'selectedCheck' => null,
    'findings' => '',
    'checkStatus' => 'passed',
    'showModal' => false,
]);

mount(function () {
    if (!Auth::user()->can('view quality_checks')) {
        abort(403);
    }
});

$inspect = function ($id) {
    if (!Auth::user()->can('manage quality_checks')) {
        abort(403);
    }
    $this->selectedCheck = QualityCheck::with('product')->findOrFail($id);
    $this->findings = $this->selectedCheck->findings_notes ?? '';
    $this->checkStatus = $this->selectedCheck->status === 'pending' ? 'passed' : $this->selectedCheck->status;
    $this->showModal = true;
};

$saveInspection = function () {
    if (!Auth::user()->can('manage quality_checks')) {
        abort(403);
    }
    $this->validate([
        'checkStatus' => 'required|in:passed,failed,quarantined',
        'findings' => 'nullable|string',
    ]);

    $this->selectedCheck->update([
        'status' => $this->checkStatus,
        'findings_notes' => $this->findings,
        'inspector_user_id' => Auth::id(),
        'inspected_at' => now(),
    ]);

    $this->showModal = false;
    $this->selectedCheck = null;
    $this->dispatch('toast', type: 'success', message: 'Quality Check processed successfully.');
};

with(function () {
    $query = QualityCheck::with(['product', 'inspector']);

    if (trim($this->search)) {
        $query->whereHas('product', function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('sku', 'like', '%' . $this->search . '%');
        });
    }

    if ($this->statusFilter) {
        $query->where('status', $this->statusFilter);
    }

    $checks = $query->latest()->paginate(10);

    // Get counts
    $pendingCount = QualityCheck::where('status', 'pending')->count();
    $passedCount = QualityCheck::where('status', 'passed')->count();
    $failedCount = QualityCheck::where('status', 'failed')->count();
    $quarantinedCount = QualityCheck::where('status', 'quarantined')->count();

    return [
        'qualityChecks' => $checks,
        'pendingCount' => $pendingCount,
        'passedCount' => $passedCount,
        'failedCount' => $failedCount,
        'quarantinedCount' => $quarantinedCount,
    ];
});

?>

<div class="p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Quality Assurance &amp; Inspections</h1>
                <p class="text-sm text-slate-500 mt-1">Audit and approve Goods Receipt Notes (GRN) and completed Manufacturing Orders (MO) before inventory releases.</p>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Pending -->
            <button wire:click="$set('statusFilter', 'pending')" class="text-left bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-amber-500 hover:shadow-md transition duration-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Audit</span>
                        <div class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ $pendingCount }}</div>
                    </div>
                </div>
            </button>

            <!-- Passed -->
            <button wire:click="$set('statusFilter', 'passed')" class="text-left bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-emerald-500 hover:shadow-md transition duration-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Passed Release</span>
                        <div class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ $passedCount }}</div>
                    </div>
                </div>
            </button>

            <!-- Failed -->
            <button wire:click="$set('statusFilter', 'failed')" class="text-left bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-rose-500 hover:shadow-md transition duration-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Failed Audit</span>
                        <div class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ $failedCount }}</div>
                    </div>
                </div>
            </button>

            <!-- Quarantined -->
            <button wire:click="$set('statusFilter', 'quarantined')" class="text-left bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-indigo-500 hover:shadow-md transition duration-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Quarantined</span>
                        <div class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ $quarantinedCount }}</div>
                    </div>
                </div>
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by product name, SKU..." class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <select wire:model.live="statusFilter" class="w-full sm:w-44 px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="passed">Passed</option>
                    <option value="failed">Failed</option>
                    <option value="quarantined">Quarantined</option>
                </select>

                @if($statusFilter || $search)
                    <button wire:click="$set('statusFilter', ''); $set('search', '');" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 rounded-xl transition">Clear Filters</button>
                @endif
            </div>
        </div>

        <!-- Inspection Logs Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-55/60">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">ID</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Product</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Source Document</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Inspector</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Audit Date</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-600">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($qualityChecks as $check)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-bold text-slate-800">QC-#{{ $check->id }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-850">{{ $check->product->name }}</div>
                                    <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $check->product->sku }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-bold px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg">
                                        {{ class_basename($check->reference_type) }} #{{ $check->reference_id }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    {{ $check->inspector->name ?? 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 text-slate-400">
                                    {{ $check->inspected_at ? $check->inspected_at->format('Y-m-d H:i') : 'Pending' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($check->status === 'pending')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                    @elseif($check->status === 'passed')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Passed</span>
                                    @elseif($check->status === 'failed')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">Failed</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">Quarantined</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if(auth()->user()->can('manage quality_checks'))
                                        <button wire:click="inspect({{ $check->id }})" class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 hover:text-indigo-700 font-bold rounded-xl text-xs transition">
                                            Audit Release
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">Read-Only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">No quality inspections found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $qualityChecks->links() }}
            </div>
        </div>
    </div>

    <!-- Audit Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>

                <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl relative z-50">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Perform Quality Inspection</h3>
                    <p class="text-xs text-slate-400 mb-4">Inspecting <b>{{ $selectedCheck->product->name }}</b> (SKU: {{ $selectedCheck->product->sku }}) for {{ class_basename($selectedCheck->reference_type) }} #{{ $selectedCheck->reference_id }}</p>
                    
                    <form wire:submit="saveInspection" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Inspection Verdict</label>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="flex items-center justify-center gap-2 p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition border-emerald-200" :class="{'bg-emerald-50/50 border-emerald-500': $wire.checkStatus === 'passed'}">
                                    <input type="radio" wire:model="checkStatus" value="passed" class="text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm font-semibold text-emerald-800">Pass</span>
                                </label>
                                
                                <label class="flex items-center justify-center gap-2 p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition border-rose-200" :class="{'bg-rose-50/50 border-rose-500': $wire.checkStatus === 'failed'}">
                                    <input type="radio" wire:model="checkStatus" value="failed" class="text-rose-600 focus:ring-rose-500">
                                    <span class="text-sm font-semibold text-rose-800">Fail</span>
                                </label>

                                <label class="flex items-center justify-center gap-2 p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition border-indigo-200" :class="{'bg-indigo-50/50 border-indigo-500': $wire.checkStatus === 'quarantined'}">
                                    <input type="radio" wire:model="checkStatus" value="quarantined" class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm font-semibold text-indigo-800">Quarantine</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Findings / Observation Notes</label>
                            <textarea wire:model="findings" rows="3" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="Write any measurements, issues, or check comments..."></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition">Confirm Audit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

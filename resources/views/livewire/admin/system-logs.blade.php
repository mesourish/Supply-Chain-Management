<?php

use App\Models\SystemLog;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $moduleFilter = '';
    public $actionFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'moduleFilter' => ['except' => ''],
        'actionFilter' => ['except' => ''],
    ];

    public function mount()
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('view system_logs')) {
            abort(403, 'Unauthorized access to system audit logs.');
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingModuleFilter()
    {
        $this->resetPage();
    }

    public function updatingActionFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->moduleFilter = '';
        $this->actionFilter = '';
        $this->resetPage();
    }

    public function with()
    {
        $query = SystemLog::with('user')->latest();

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('action', 'like', '%' . $this->search . '%')
                  ->orWhere('module', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function ($uq) {
                      $uq->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if (!empty($this->moduleFilter)) {
            $query->where('module', $this->moduleFilter);
        }

        if (!empty($this->actionFilter)) {
            $query->where('action', $this->actionFilter);
        }

        // Get unique actions and modules for dropdown filters
        $modules = SystemLog::select('module')->distinct()->orderBy('module')->pluck('module');
        $actions = SystemLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return [
            'logs' => $query->paginate(20),
            'modules' => $modules,
            'actions' => $actions,
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">System Audit logs</h1>
            <p class="text-xs text-gray-500 mt-1">Real-time recording of security events, document edits, login metrics, and administrative activities.</p>
        </div>
        
        @if(!empty($search) || !empty($moduleFilter) || !empty($actionFilter))
            <button type="button" wire:click="clearFilters" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-150 transition-colors shadow-sm self-start md:self-auto">
                Reset All Filters
            </button>
        @endif
    </div>

    <!-- Filters Panel -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-150 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Search audit trails</label>
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full text-xs border border-slate-200 focus:border-indigo-500 focus:ring-0 rounded-xl py-2 px-3 pl-8 text-slate-800 font-semibold" placeholder="Search logs, actions, users...">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        </div>

        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Filter by Module</label>
            <select wire:model.live="moduleFilter" class="w-full text-xs border border-slate-200 focus:border-indigo-500 focus:ring-0 rounded-xl py-2 px-3 text-slate-800 font-bold cursor-pointer">
                <option value="">-- All Modules --</option>
                @foreach($modules as $mod)
                    <option value="{{ $mod }}">{{ $mod }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Filter by Action</label>
            <select wire:model.live="actionFilter" class="w-full text-xs border border-slate-200 focus:border-indigo-500 focus:ring-0 rounded-xl py-2 px-3 text-slate-800 font-bold cursor-pointer">
                <option value="">-- All Actions --</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}">{{ ucfirst(str_replace('_', ' ', $act)) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-3xl border border-slate-150">
        <div class="p-6 text-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-150">
                    <thead class="bg-slate-50/50">
                        <tr class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <th class="px-6 py-3 rounded-l-xl">Timestamp</th>
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Event / Module</th>
                            <th class="px-6 py-3">Description</th>
                            <th class="px-6 py-3">Metadata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white text-xs">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                <!-- Timestamp -->
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-500 font-medium">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                    <span class="block text-[10px] text-slate-400 font-semibold">{{ $log->created_at->diffForHumans() }}</span>
                                </td>
                                <!-- User -->
                                <td class="px-6 py-4 whitespace-nowrap font-semibold">
                                    @if($log->user)
                                        <div class="text-slate-850">{{ $log->user->name }}</div>
                                        <div class="text-[10px] text-slate-450 font-mono font-medium">{{ $log->user->email }}</div>
                                    @else
                                        <span class="text-slate-400 italic">System Auto / Guest</span>
                                    @endif
                                </td>
                                <!-- Event / Module -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 inline-flex text-[10px] leading-5 font-bold rounded-full border border-indigo-100 bg-indigo-50 text-indigo-700 capitalize">
                                        {{ str_replace('_', ' ', $log->action) }}
                                    </span>
                                    <span class="block text-[10px] text-slate-450 mt-1 font-bold">{{ $log->module }}</span>
                                </td>
                                <!-- Description -->
                                <td class="px-6 py-4 text-slate-650 font-medium whitespace-normal max-w-sm leading-relaxed">
                                    {{ $log->description }}
                                </td>
                                <!-- Metadata -->
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-[10px] text-slate-400 leading-normal">
                                    <div><span class="font-bold">IP:</span> {{ $log->ip_address ?: '127.0.0.1' }}</div>
                                    <div class="truncate max-w-[150px] mt-0.5" title="{{ $log->user_agent }}"><span class="font-bold">UA:</span> {{ $log->user_agent ?: 'N/A' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic font-medium">No system audit trails match specified criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>

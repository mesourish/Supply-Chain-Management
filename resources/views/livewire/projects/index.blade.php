<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Customer;

new class extends Component {
    public $name = '';
    public $code = '';
    public $customer_id = '';
    public $status = 'planning';
    public $start_date = '';
    public $end_date = '';
    public $budget = 0.00;
    public $description = '';

    public $showCreateModal = false;

    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->start_date = date('Y-m-d');
        $this->generateProjectCode();
    }

    public function getProjectsList()
    {
        return Project::with(['customer', 'milestones'])->latest()->get();
    }

    public function getCustomersList()
    {
        return Customer::orderBy('name')->get();
    }

    public function generateProjectCode()
    {
        $this->code = 'PRJ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public function resetForm()
    {
        $this->name = '';
        $this->customer_id = '';
        $this->status = 'planning';
        $this->start_date = date('Y-m-d');
        $this->end_date = '';
        $this->budget = 0.00;
        $this->description = '';
        $this->generateProjectCode();
    }

    public function saveProject()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:projects,code',
            'start_date' => 'required|date',
            'budget' => 'required|numeric|min:0',
        ]);

        Project::create([
            'name' => $this->name,
            'code' => $this->code,
            'customer_id' => $this->customer_id ?: null,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date ?: null,
            'budget' => $this->budget ?: 0,
            'description' => $this->description,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('message', 'Project initialized successfully.');
    }
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">

    @if(session()->has('message'))
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm font-semibold text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Project SCM Portfolios</h1>
            <p class="text-xs text-gray-500 mt-1">Manage corporate supply contracts, track project timelines, execute milestone-based stock reservations, and trigger physical tool transfers.</p>
        </div>
        <button type="button" wire:click="$toggle('showCreateModal')" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
            + Initialize Project
        </button>
    </div>

    <!-- Projects Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($this->getProjectsList() as $prj)
            @php
                $totalMilestones = $prj->milestones->count();
                $completedMilestones = $prj->milestones->where('status', 'completed')->count();
                $progress = $totalMilestones > 0 ? ($completedMilestones / $totalMilestones) * 100 : 0;
                
                $statusColors = 'bg-gray-50 text-gray-700 border-gray-150';
                if ($prj->status === 'active') $statusColors = 'bg-indigo-50 text-indigo-700 border-indigo-100';
                elseif ($prj->status === 'completed') $statusColors = 'bg-green-50 text-green-700 border-green-100';
                elseif ($prj->status === 'on_hold') $statusColors = 'bg-amber-50 text-amber-700 border-amber-100';
            @endphp
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 flex flex-col justify-between group hover:shadow-md transition-shadow relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-indigo-500"></div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-mono font-bold text-gray-400">{{ $prj->code }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border {{ $statusColors }}">
                            {{ $prj->status }}
                        </span>
                    </div>

                    <div>
                        <h3 class="text-base font-extrabold text-gray-900 group-hover:text-indigo-600 transition-colors">
                            <a href="{{ route('projects.show', $prj->id) }}">{{ $prj->name }}</a>
                        </h3>
                        <p class="text-xs text-indigo-600 font-semibold mt-0.5">{{ $prj->customer->name ?? 'Internal Project' }}</p>
                    </div>

                    <!-- Progress bar -->
                    <div class="space-y-1">
                        <div class="flex justify-between text-[10px] font-bold text-gray-400">
                            <span>Milestones progress</span>
                            <span>{{ $completedMilestones }}/{{ $totalMilestones }} completed</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 mt-6 flex justify-between items-center text-xs">
                    <div>
                        <span class="text-gray-400 font-bold uppercase block text-[8px]">Budget</span>
                        <span class="font-extrabold text-gray-800 font-mono">${{ number_format($prj->budget, 2) }}</span>
                    </div>
                    <a href="{{ route('projects.show', $prj->id) }}" class="inline-flex items-center justify-center px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-lg font-bold transition-all duration-200">
                        View Dashboard &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 bg-gray-50 border border-dashed border-gray-350 rounded-3xl text-gray-500 font-medium">
                📁 No projects initialized yet. Click "+ Initialize Project" to compile SCM contracts.
            </div>
        @endforelse
    </div>

    <!-- Create Project Modal -->
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showCreateModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Initialize New SCM Project</h3>

                    <form wire:submit.prevent="saveProject" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Project Name *" />
                            <x-text-input wire:model="name" type="text" class="mt-1 block w-full text-xs" required />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Project Unique Code *" />
                                <x-text-input wire:model="code" type="text" class="mt-1 block w-full text-xs font-mono font-bold" required />
                            </div>
                            <div>
                                <x-input-label value="Associated Client (Customer)" />
                                <select wire:model="customer_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                    <option value="">-- Choose Client --</option>
                                    @foreach($this->getCustomersList() as $cust)
                                        <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Timeline Start *" />
                                <input type="date" wire:model="start_date" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700" required>
                            </div>
                            <div>
                                <x-input-label value="Timeline End (Target)" />
                                <input type="date" wire:model="end_date" class="mt-1 block w-full rounded-xl border-gray-300 text-xs font-semibold text-gray-700">
                            </div>
                            <div>
                                <x-input-label value="Allocated Budget ($) *" />
                                <input type="number" step="0.01" min="0" wire:model="budget" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                            </div>
                            <div>
                                <x-input-label value="Initial Status" />
                                <select wire:model="status" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                    <option value="planning">Planning Mode</option>
                                    <option value="active">Active Execution</option>
                                    <option value="on_hold">On Hold</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Detailed Project Description" />
                            <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Summarize deliverables..."></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Initialize</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>

<?php

use Livewire\Volt\Component;
use App\Models\CrmLead;
use App\Models\CrmActivity;
use App\Models\User;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationItem;

new class extends Component {
    // Lead Form Fields
    public $title = '';
    public $company_name = '';
    public $contact_name = '';
    public $email = '';
    public $phone = '';
    public $deal_value = 0;
    public $pipeline_stage = 'new';
    public $deal_probability = 10;
    public $source = '';
    public $notes = '';
    public $assigned_user_id = '';
    
    // UI Modals & Actions
    public $showCreateModal = false;
    public $showActivityModal = false;
    public $selectedLeadId = null;
    
    // Activity Form Fields
    public $activityType = 'call';
    public $activityDescription = '';
    public $activityDate = '';
    
    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->activityDate = date('Y-m-d');
    }

    public function getLeadsList()
    {
        return CrmLead::with(['assignedUser', 'activities.user'])->latest()->get();
    }

    public function getUsersList()
    {
        return User::orderBy('name')->get();
    }

    public function createLead()
    {
        $this->resetInputFields();
        $this->assigned_user_id = auth()->id();
        $this->showCreateModal = true;
    }

    public function resetInputFields()
    {
        $this->title = '';
        $this->company_name = '';
        $this->contact_name = '';
        $this->email = '';
        $this->phone = '';
        $this->deal_value = 0;
        $this->pipeline_stage = 'new';
        $this->deal_probability = 10;
        $this->source = '';
        $this->notes = '';
        $this->selectedLeadId = null;
    }

    public function saveLead()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'deal_value' => 'required|numeric|min:0',
            'pipeline_stage' => 'required|in:new,contacted,proposal,negotiation,won,lost',
            'deal_probability' => 'required|integer|min:0|max:100',
        ]);

        CrmLead::create([
            'title' => $this->title,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'deal_value' => $this->deal_value,
            'pipeline_stage' => $this->pipeline_stage,
            'deal_probability' => $this->deal_probability,
            'source' => $this->source,
            'notes' => $this->notes,
            'assigned_user_id' => $this->assigned_user_id ?: null,
        ]);

        $this->showCreateModal = false;
        session()->flash('message', 'Lead added to pipeline successfully.');
    }

    public function moveStage($id, $newStage)
    {
        $lead = CrmLead::findOrFail($id);
        $prob = 10;
        if ($newStage === 'contacted') $prob = 30;
        elseif ($newStage === 'proposal') $prob = 60;
        elseif ($newStage === 'negotiation') $prob = 80;
        elseif ($newStage === 'won') $prob = 100;
        elseif ($newStage === 'lost') $prob = 0;

        $lead->update([
            'pipeline_stage' => $newStage,
            'deal_probability' => $prob
        ]);

        // Auto convert to Customer if Won
        if ($newStage === 'won' && !$lead->customer_id) {
            $customer = Customer::create([
                'name' => $lead->contact_name,
                'contact_person' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company_name' => $lead->company_name,
            ]);
            $lead->update(['customer_id' => $customer->id]);
        }

        session()->flash('message', 'Lead pipeline stage moved successfully.');
    }

    public function openActivityModal($id)
    {
        $this->selectedLeadId = $id;
        $this->activityType = 'call';
        $this->activityDescription = '';
        $this->activityDate = date('Y-m-d');
        $this->showActivityModal = true;
    }

    public function logActivity()
    {
        $this->validate([
            'activityType' => 'required|in:call,email,meeting,note',
            'activityDescription' => 'required|string|max:1000',
            'activityDate' => 'required|date',
        ]);

        CrmActivity::create([
            'crm_lead_id' => $this->selectedLeadId,
            'type' => $this->activityType,
            'description' => $this->activityDescription,
            'activity_date' => $this->activityDate,
            'user_id' => auth()->id(),
        ]);

        $this->showActivityModal = false;
        session()->flash('message', 'Pipeline interaction activity logged successfully.');
    }

    public function convertToQuote($leadId)
    {
        $lead = CrmLead::findOrFail($leadId);
        
        // Auto convert to customer first if not already done
        if (!$lead->customer_id) {
            $customer = Customer::create([
                'name' => $lead->contact_name,
                'contact_person' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company_name' => $lead->company_name,
            ]);
            $lead->update(['customer_id' => $customer->id]);
        }

        // Create standard draft quote
        $quotation = Quotation::create([
            'reference_no' => 'QTE-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'customer_id' => $lead->customer_id,
            'crm_lead_id' => $lead->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(30)->toDateString(),
            'total_amount' => $lead->deal_value,
        ]);

        session()->flash('message', "Lead converted to Sales Quote successfully: Ref {$quotation->reference_no}");
    }

    public function convertToCustomer($leadId)
    {
        $lead = CrmLead::findOrFail($leadId);
        if (!$lead->customer_id) {
            $customer = Customer::create([
                'name' => $lead->contact_name,
                'contact_person' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company_name' => $lead->company_name,
            ]);
            $lead->update(['customer_id' => $customer->id]);
            session()->flash('message', "Lead successfully converted to Customer Profile: {$customer->name}!");
        } else {
            session()->flash('message', "Lead is already linked to a Customer Profile.");
        }
    }
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">
    
    @if(session()->has('message'))
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm font-semibold text-sm" role="alert">
            {{ session('message') }}
        </div>
    @endif

    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">CRM Deal Pipeline</h1>
            <p class="text-xs text-gray-500 mt-1">Track customer leads, record dynamic interactions, assign weights, and trigger SCM supply orders.</p>
        </div>
        <button type="button" wire:click="createLead" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
            + New Lead Prospect
        </button>
    </div>

    <!-- Pipeline Analytics Metrics -->
    @php
        $leads = $this->getLeadsList();
        $totalPipeline = $leads->whereIn('pipeline_stage', ['new', 'contacted', 'proposal', 'negotiation'])->sum('deal_value');
        $weightedPipeline = 0;
        foreach($leads->whereIn('pipeline_stage', ['new', 'contacted', 'proposal', 'negotiation']) as $ld) {
            $weightedPipeline += ($ld->deal_value * $ld->deal_probability / 100);
        }
        $winsCount = $leads->where('pipeline_stage', 'won')->count();
        $totalLeads = $leads->count();
        $winRate = $totalLeads > 0 ? ($winsCount / $totalLeads) * 100 : 0;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Active Pipeline</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">${{ number_format($totalPipeline, 2) }}</h3>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Weighted Pipeline</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">${{ number_format($weightedPipeline, 2) }}</h3>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-sky-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Closed Deal Wins</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ $winsCount }} deals won</h3>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Pipeline Win Rate</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ number_format($winRate, 1) }}%</h3>
        </div>
    </div>

    <!-- Kanban Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 items-start">
        
        @foreach([
            'new' => ['name' => 'New Lead', 'bg' => 'bg-gray-100/50', 'border' => 'border-gray-200', 'text' => 'text-gray-600'],
            'contacted' => ['name' => 'Contacted', 'bg' => 'bg-sky-50/30', 'border' => 'border-sky-100', 'text' => 'text-sky-600'],
            'proposal' => ['name' => 'Proposal Sent', 'bg' => 'bg-indigo-50/20', 'border' => 'border-indigo-100', 'text' => 'text-indigo-600'],
            'negotiation' => ['name' => 'Negotiation', 'bg' => 'bg-amber-50/20', 'border' => 'border-amber-100', 'text' => 'text-amber-600'],
            'won' => ['name' => 'Deals Won 🎉', 'bg' => 'bg-emerald-50/30', 'border' => 'border-emerald-100', 'text' => 'text-emerald-600'],
            'lost' => ['name' => 'Deals Lost', 'bg' => 'bg-rose-50/20', 'border' => 'border-rose-100', 'text' => 'text-rose-600']
        ] as $stageKey => $stageVal)
            
            @php
                $stageLeads = $leads->where('pipeline_stage', $stageKey);
                $stageTotal = $stageLeads->sum('deal_value');
            @endphp

            <div class="rounded-3xl p-4 {{ $stageVal['bg'] }} border {{ $stageVal['border'] }} space-y-4">
                <!-- Column Header -->
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <div>
                        <h4 class="text-sm font-black text-gray-900">{{ $stageVal['name'] }}</h4>
                        <span class="text-[10px] text-gray-400 font-bold font-mono">{{ $stageLeads->count() }} deals</span>
                    </div>
                    <span class="text-xs font-black {{ $stageVal['text'] }} font-mono">${{ number_format($stageTotal, 0) }}</span>
                </div>

                <!-- Column Cards list -->
                <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                    @forelse($stageLeads as $lead)
                        <div class="bg-white p-3 rounded-2xl border border-gray-150 shadow-sm relative group hover:shadow-md transition-shadow">
                            
                            <!-- Probability Indicator dot -->
                            <div class="absolute top-3 right-3 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full 
                                    {{ $lead->deal_probability >= 80 ? 'bg-emerald-500' : ($lead->deal_probability >= 50 ? 'bg-amber-400' : 'bg-gray-400') }}">
                                </span>
                                <span class="text-[8px] font-mono font-bold text-gray-400">{{ $lead->deal_probability }}%</span>
                            </div>

                            <div class="text-xs font-extrabold text-gray-900 leading-snug pr-8">{{ $lead->title }}</div>
                            <div class="text-[10px] font-bold text-indigo-600 mt-0.5 truncate">{{ $lead->company_name ?: 'Private Prospect' }}</div>
                            <div class="text-[10px] text-gray-400 mt-1">Rep: {{ $lead->contact_name }}</div>

                            <!-- Deal Value -->
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-[10px] text-gray-400 font-bold uppercase">Deal value:</span>
                                <span class="text-xs font-mono font-black text-gray-900">${{ number_format($lead->deal_value, 2) }}</span>
                            </div>

                            <!-- Actions Row -->
                            <div class="border-t border-gray-50 pt-2.5 mt-2.5 flex items-center justify-between gap-1 text-[9px] font-bold">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openActivityModal({{ $lead->id }})" class="text-gray-400 hover:text-indigo-600 flex items-center gap-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                        Log
                                    </button>
                                    @if(!$lead->customer_id)
                                        <button type="button" wire:click="convertToCustomer({{ $lead->id }})" class="text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5" title="Convert to Customer Directory Profile">
                                            👤 Convert
                                        </button>
                                    @else
                                        <span class="text-gray-400 cursor-default" title="Converted Customer Client">👤 Client</span>
                                    @endif
                                </div>
                                
                                <div class="flex items-center gap-1.5">
                                    @if($stageKey !== 'won' && $stageKey !== 'lost')
                                        <select 
                                            onchange="@this.moveStage({{ $lead->id }}, this.value)"
                                            class="p-0.5 text-[8px] bg-slate-50 border-gray-200 text-gray-600 rounded focus:ring-0 focus:border-indigo-400"
                                        >
                                            <option value="">Move...</option>
                                            @foreach(['new' => 'New', 'contacted' => 'Contacted', 'proposal' => 'Proposal', 'negotiation' => 'Negotiation', 'won' => 'Won', 'lost' => 'Lost'] as $k => $v)
                                                @if($k !== $stageKey)
                                                    <option value="{{ $k }}">{{ $v }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    @endif

                                    @if($stageKey === 'won')
                                        <button type="button" wire:click="convertToQuote({{ $lead->id }})" class="text-emerald-600 hover:text-emerald-800">
                                            + Quote
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-[10px] text-gray-400 italic">No prospects</div>
                    @endforelse
                </div>
            </div>

        @endforeach

    </div>

    <!-- Create Prospect Lead Modal -->
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showCreateModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Create Lead Prospect</h3>

                    <form wire:submit.prevent="saveLead" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <x-input-label value="Deal Title / Opportunity *" />
                                <x-text-input wire:model="title" type="text" class="mt-1 block w-full text-xs" placeholder="e.g. Bulk motors bulk purchase order" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Prospect Company Name" />
                                <x-text-input wire:model="company_name" type="text" class="mt-1 block w-full text-xs" placeholder="e.g. Sourish Construction Ltd" />
                            </div>
                            <div>
                                <x-input-label value="Contact Person Name *" />
                                <x-text-input wire:model="contact_name" type="text" class="mt-1 block w-full text-xs" required />
                            </div>
                            <div>
                                <x-input-label value="Contact Email *" />
                                <x-text-input wire:model="email" type="email" class="mt-1 block w-full text-xs" required />
                            </div>
                            <div>
                                <x-input-label value="Phone Number" />
                                <x-text-input wire:model="phone" type="text" class="mt-1 block w-full text-xs" />
                            </div>
                            <div>
                                <x-input-label value="Est. Deal Value ($) *" />
                                <x-text-input wire:model="deal_value" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs" required />
                            </div>
                            <div>
                                <x-input-label value="Initial Pipeline Stage" />
                                <select wire:model="pipeline_stage" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                    <option value="new">New Lead</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="proposal">Proposal Sent</option>
                                    <option value="negotiation">Negotiation</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Assigned User" />
                                <select wire:model="assigned_user_id" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700">
                                    <option value="">-- Choose User --</option>
                                    @foreach($this->getUsersList() as $usr)
                                        <option value="{{ $usr->id }}">{{ $usr->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Source / Origin" />
                                <x-text-input wire:model="source" type="text" class="mt-1 block w-full text-xs" placeholder="e.g. LinkedIn, Website Quote form" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Deal Notes" />
                            <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Describe the requirements..."></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Add Prospect</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Log Activity Modal -->
    @if($showActivityModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showActivityModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-4">Log Deal Interaction Activity</h3>

                    <form wire:submit.prevent="logActivity" class="space-y-4 text-xs font-semibold text-gray-700">
                        <div>
                            <x-input-label value="Interaction Type *" />
                            <select wire:model="activityType" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700" required>
                                <option value="call">📞 Phone Call</option>
                                <option value="email">📧 Email Sent / Received</option>
                                <option value="meeting">🤝 Meeting Held</option>
                                <option value="note">📝 Internal Deal Note</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Activity Date *" />
                            <x-text-input wire:model="activityDate" type="date" class="mt-1 block w-full text-xs" required />
                        </div>
                        <div>
                            <x-input-label value="Description of Conversation *" />
                            <textarea wire:model="activityDescription" rows="4" class="mt-1 block w-full rounded-xl border-gray-300 text-xs" placeholder="Write exactly what was discussed and what follow-ups are needed..." required></textarea>
                            <x-input-error :messages="$errors->get('activityDescription')" class="mt-1" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150">
                            <button type="button" wire:click="$set('showActivityModal', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Record Interaction</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

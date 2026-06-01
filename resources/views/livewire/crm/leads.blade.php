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
    public $viewMode = 'list'; // 'kanban' or 'list'
    public $showCreateModal = false;
    public $showActivityModal = false;
    public $selectedLeadId = null;
    
    // Activity Form Fields
    public $activityType = 'call';
    public $activityDescription = '';
    public $activityDate = '';

    // Lead Details Modal state
    public $showDetailsModal = false;
    public $isEditingDetails = false;
    public $selectedLead = null;
    
    // Edit Lead Form Fields
    public $editTitle = '';
    public $editCompanyName = '';
    public $editContactName = '';
    public $editEmail = '';
    public $editPhone = '';
    public $editDealValue = 0;
    public $editPipelineStage = 'new';
    public $editDealProbability = 10;
    public $editSource = '';
    public $editNotes = '';
    public $editAssignedUserId = '';
    
    public function mount()
    {
        if (!auth()->user()->can('view dashboard')) { abort(403); }
        $this->activityDate = date('Y-m-d');
    }

    public function viewLead($id)
    {
        $lead = CrmLead::with(['assignedUser', 'activities.user'])->findOrFail($id);
        $this->selectedLeadId = $id;
        $this->selectedLead = $lead;
        
        // Map edit fields
        $this->editTitle = $lead->title;
        $this->editCompanyName = $lead->company_name;
        $this->editContactName = $lead->contact_name;
        $this->editEmail = $lead->email;
        $this->editPhone = $lead->phone;
        $this->editDealValue = $lead->deal_value;
        $this->editPipelineStage = $lead->pipeline_stage;
        $this->editDealProbability = $lead->deal_probability;
        $this->editSource = $lead->source;
        $this->editNotes = $lead->notes;
        $this->editAssignedUserId = $lead->assigned_user_id;

        $this->isEditingDetails = false;
        $this->showDetailsModal = true;
    }

    public function updateLeadDetails()
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editContactName' => 'required|string|max:255',
            'editEmail' => 'required|email|max:255',
            'editDealValue' => 'required|numeric|min:0',
            'editPipelineStage' => 'required|in:new,contacted,proposal,negotiation,won,lost',
            'editDealProbability' => 'required|integer|min:0|max:100',
        ]);

        $lead = CrmLead::findOrFail($this->selectedLeadId);
        
        // Check if stage transitioned to Won, dynamically create customer first if not already done
        if ($this->editPipelineStage === 'won' && !$lead->customer_id) {
            $customer = Customer::create([
                'name' => $this->editCompanyName ?: $this->editContactName,
                'contact_person' => $this->editContactName,
                'email' => $this->editEmail,
                'phone' => $this->editPhone,
                'company_name' => $this->editCompanyName,
            ]);
            $lead->customer_id = $customer->id;
        }

        $lead->update([
            'title' => $this->editTitle,
            'company_name' => $this->editCompanyName,
            'contact_name' => $this->editContactName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone,
            'deal_value' => $this->editDealValue,
            'pipeline_stage' => $this->editPipelineStage,
            'deal_probability' => $this->editDealProbability,
            'source' => $this->editSource,
            'notes' => $this->editNotes,
            'assigned_user_id' => $this->editAssignedUserId ?: null,
            'customer_id' => $lead->customer_id,
        ]);

        $this->selectedLead = $lead->load(['assignedUser', 'activities.user']);
        $this->isEditingDetails = false;
        $this->dispatch('toast', type: 'success', message:  'Lead details updated successfully.');
    }

    public function deleteLead($id)
    {
        $lead = CrmLead::findOrFail($id);
        $lead->delete();
        $this->showDetailsModal = false;
        $this->dispatch('toast', type: 'success', message:  'Lead deleted successfully.');
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
        $this->dispatch('toast', type: 'success', message:  'Lead added to pipeline successfully.');
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
                'name' => $lead->company_name ?: $lead->contact_name,
                'contact_person' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company_name' => $lead->company_name,
            ]);
            $lead->update(['customer_id' => $customer->id]);
        }

        $this->dispatch('toast', type: 'success', message:  'Lead pipeline stage moved successfully.');
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
        $this->dispatch('toast', type: 'success', message:  'Pipeline interaction activity logged successfully.');
    }

    public function convertToQuote($leadId)
    {
        $lead = CrmLead::findOrFail($leadId);
        
        // Auto convert to customer first if not already done
        if (!$lead->customer_id) {
            $customer = Customer::create([
                'name' => $lead->company_name ?: $lead->contact_name,
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

        // Create a QuotationItem matching the lead's deal value
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => "Lead Opportunity: " . $lead->title,
            'quantity' => 1,
            'unit_price' => $lead->deal_value,
            'total_price' => $lead->deal_value,
        ]);

        // Update Lead status to proposal/60%
        $lead->update([
            'pipeline_stage' => 'proposal',
            'deal_probability' => 60,
        ]);

        // Log Timeline Interaction Activity
        CrmActivity::create([
            'crm_lead_id' => $lead->id,
            'type' => 'note',
            'description' => "Lead converted to Sales Quote successfully: Ref {$quotation->reference_no}",
            'activity_date' => now()->toDateString(),
            'user_id' => auth()->id() ?? User::first()?->id,
        ]);

        $this->dispatch('toast', type: 'success', message:  "Lead converted to Sales Quote successfully: Ref {$quotation->reference_no}");
    }

    public function convertToCustomer($leadId)
    {
        $lead = CrmLead::findOrFail($leadId);
        if (!$lead->customer_id) {
            $customer = Customer::create([
                'name' => $lead->company_name ?: $lead->contact_name,
                'contact_person' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
            ]);
            $lead->update(['customer_id' => $customer->id]);
            $this->dispatch('toast', type: 'success', message:  "Lead successfully converted to Customer Profile: {$customer->name}!");
        } else {
            $this->dispatch('toast', type: 'success', message:  "Lead is already linked to a Customer Profile.");
        }
    }
};

?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-8">
    
    

    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">CRM Deal Pipeline</h1>
            <p class="text-xs text-gray-500 mt-1">Track customer leads, record dynamic interactions, assign weights, and trigger SCM supply orders.</p>
        </div>
        <div class="flex items-center gap-3">
            <!-- View Toggle -->
            <div class="bg-gray-100 p-1 rounded-xl flex items-center shadow-inner">
                <button type="button" wire:click="$set('viewMode', 'list')" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors {{ $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    List View
                </button>
                <button type="button" wire:click="$set('viewMode', 'kanban')" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors {{ $viewMode === 'kanban' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Kanban Board
                </button>
            </div>
            
            <button type="button" wire:click="createLead" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-md transition-colors">
                + New Lead Prospect
            </button>
        </div>
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
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($totalPipeline, 2) }}</h3>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden">
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <span class="text-xs text-gray-400 font-extrabold uppercase">Weighted Pipeline</span>
            <h3 class="text-2xl font-black text-gray-900 font-mono mt-2">{{ setting('currency_symbol', '$') }}{{ number_format($weightedPipeline, 2) }}</h3>
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

    @if($viewMode === 'kanban')
    <!-- Kanban Responsive Horizontally Scrollable Board -->
    <div class="flex overflow-x-auto gap-6 pb-6 select-none scrollbar-thin" style="scrollbar-width: thin; -webkit-overflow-scrolling: touch;">
        
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

            <div x-data="{ draggingOver: false }"
                 x-on:dragenter.prevent="draggingOver = true"
                 x-on:dragleave.prevent="draggingOver = false"
                 x-on:dragover.prevent=""
                 x-on:drop="draggingOver = false; const leadId = event.dataTransfer.getData('text/plain'); $wire.moveStage(leadId, '{{ $stageKey }}')"
                 class="flex-shrink-0 w-[290px] lg:w-[310px] rounded-3xl p-4 transition-all duration-200 border space-y-4 {{ $stageVal['bg'] }} {{ $stageVal['border'] }}"
                 :class="{ 'ring-2 ring-indigo-500 bg-indigo-50/15 border-indigo-200 shadow-md scale-[1.01]': draggingOver }"
            >
                <!-- Column Header -->
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <div>
                        <h4 class="text-sm font-black text-gray-900">{{ $stageVal['name'] }}</h4>
                        <span class="text-[10px] text-gray-400 font-bold font-mono">{{ $stageLeads->count() }} deals</span>
                    </div>
                    <span class="text-xs font-black {{ $stageVal['text'] }} font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($stageTotal, 0) }}</span>
                </div>

                <!-- Column Cards list -->
                <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                    @forelse($stageLeads as $lead)
                        <div draggable="true"
                             x-on:dragstart="event.dataTransfer.setData('text/plain', {{ $lead->id }})"
                             wire:click="viewLead({{ $lead->id }})"
                             class="bg-white p-4 rounded-2xl border border-gray-150 shadow-sm relative group hover:shadow-md hover:border-indigo-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"
                        >
                            
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
                                <span class="text-xs font-mono font-black text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($lead->deal_value, 2) }}</span>
                            </div>

                            <!-- Actions Row -->
                            <div class="border-t border-gray-50 pt-2.5 mt-2.5 flex items-center justify-between gap-1 text-[9px] font-bold">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click.stop="openActivityModal({{ $lead->id }})" class="text-gray-400 hover:text-indigo-600 flex items-center gap-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                        Log
                                    </button>
                                    @if(!$lead->customer_id)
                                        <button type="button" wire:click.stop="convertToCustomer({{ $lead->id }})" class="text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5" title="Convert to Customer Directory Profile">
                                            👤 Convert
                                        </button>
                                    @else
                                        <span class="text-gray-400 cursor-default" title="Converted Customer Client" onclick="event.stopPropagation()">👤 Client</span>
                                    @endif
                                </div>
                                
                                <div class="flex items-center gap-1.5" onclick="event.stopPropagation()">
                                    @if($stageKey !== 'won' && $stageKey !== 'lost')
                                        <select 
                                            onchange="event.stopPropagation(); @this.moveStage({{ $lead->id }}, this.value)"
                                            onclick="event.stopPropagation()"
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
                                        <button type="button" wire:click.stop="convertToQuote({{ $lead->id }})" class="text-emerald-600 hover:text-emerald-800">
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
    @else
    <!-- List View -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-150 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/50 text-xs text-gray-400 font-extrabold uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 border-b border-gray-100">Prospect / Company</th>
                        <th class="px-6 py-4 border-b border-gray-100">Stage</th>
                        <th class="px-6 py-4 border-b border-gray-100">Value</th>
                        <th class="px-6 py-4 border-b border-gray-100">Probability</th>
                        <th class="px-6 py-4 border-b border-gray-100">Rep</th>
                        <th class="px-6 py-4 border-b border-gray-100 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-gray-50/50 transition-colors group cursor-pointer" wire:click="viewLead({{ $lead->id }})">
                            <td class="px-6 py-4">
                                <div class="font-extrabold text-gray-900 text-sm">{{ $lead->title }}</div>
                                <div class="text-xs font-bold text-indigo-600 mt-0.5">{{ $lead->company_name ?: 'Private Prospect' }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">Contact: {{ $lead->contact_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $stageNames = [
                                        'new' => 'New Lead', 'contacted' => 'Contacted',
                                        'proposal' => 'Proposal Sent', 'negotiation' => 'Negotiating',
                                        'won' => 'Won 🎉', 'lost' => 'Lost'
                                    ];
                                    $badgeClr = 'bg-gray-100 text-gray-700 border-gray-200';
                                    if ($lead->pipeline_stage === 'won') $badgeClr = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                    elseif ($lead->pipeline_stage === 'lost') $badgeClr = 'bg-rose-50 text-rose-700 border-rose-100';
                                    elseif ($lead->pipeline_stage === 'negotiation') $badgeClr = 'bg-amber-50 text-amber-700 border-amber-100';
                                    elseif ($lead->pipeline_stage === 'proposal') $badgeClr = 'bg-indigo-50 text-indigo-700 border-indigo-100';
                                @endphp
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border {{ $badgeClr }}">
                                    {{ $stageNames[$lead->pipeline_stage] ?? $lead->pipeline_stage }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono font-black text-gray-900 text-sm">
                                {{ setting('currency_symbol', '$') }}{{ number_format($lead->deal_value, 2) }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full {{ $lead->deal_probability >= 80 ? 'bg-emerald-500' : ($lead->deal_probability >= 50 ? 'bg-amber-400' : 'bg-gray-400') }}" style="width: {{ $lead->deal_probability }}%"></div>
                                    </div>
                                    <span class="text-xs font-mono font-bold text-gray-500">{{ $lead->deal_probability }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-gray-700">
                                {{ $lead->assignedUser->name ?? '--' }}
                            </td>
                            <td class="px-6 py-4 text-right text-xs font-bold" onclick="event.stopPropagation()">
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" wire:click.stop="openActivityModal({{ $lead->id }})" class="text-gray-400 hover:text-indigo-600 transition-colors">
                                        Log
                                    </button>
                                    @if($lead->pipeline_stage === 'won')
                                        <button type="button" wire:click.stop="convertToQuote({{ $lead->id }})" class="text-emerald-600 hover:text-emerald-800 transition-colors">
                                            + Quote
                                        </button>
                                    @endif
                                    @if(!$lead->customer_id)
                                        <button type="button" wire:click.stop="convertToCustomer({{ $lead->id }})" class="text-indigo-600 hover:text-indigo-800 transition-colors">
                                            👤 Convert
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 italic">No prospects found in the pipeline.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

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

    <!-- CrmLead Details & printable A4 Profile Modal -->
    @if($showDetailsModal && $selectedLead)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-60 no-print" wire:click="$set('showDetailsModal', false)"></div>
                
                <div class="inline-block w-full max-w-3xl p-8 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl relative z-50 border border-slate-100">
                    
                    <!-- View Modal Header (No Print) -->
                    <div class="flex justify-between items-center border-b border-slate-100 pb-4 mb-6 no-print">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Lead Prospect Profile</h3>
                            <p class="text-xs text-slate-400">View details, log activities, print prospectus, or edit parameters.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(!$isEditingDetails)
                                <button type="button" wire:click="$set('isEditingDetails', true)" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                                    Edit Details
                                </button>
                                <button onclick="window.print()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    Print / PDF
                                </button>
                            @else
                                <button type="button" wire:click="$set('isEditingDetails', false)" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                                    Cancel
                                </button>
                            @endif
                            <button wire:click="$set('showDetailsModal', false)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <style>
                        @media print {
                            body {
                                background: white !important;
                                color: black !important;
                                -webkit-print-color-adjust: exact !important;
                                print-color-adjust: exact !important;
                            }
                            body * {
                                visibility: hidden;
                            }
                            #printable-lead-area, #printable-lead-area * {
                                visibility: visible !important;
                            }
                            #printable-lead-area {
                                position: fixed !important;
                                left: 0 !important;
                                top: 0 !important;
                                width: 100% !important;
                                height: 100% !important;
                                z-index: 9999999 !important;
                                background: white !important;
                                color: black !important;
                                padding: 1.5cm !important;
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

                    <!-- Modal Body Content -->
                    <div id="printable-lead-area">
                        @if(!$isEditingDetails)
                            <!-- READ ONLY DETAILS -->
                            <div class="space-y-6">
                                <!-- Title banner -->
                                <div class="flex justify-between items-start border-b border-slate-100 pb-4">
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900 tracking-tight">{{ $selectedLead->title }}</h2>
                                        <div class="text-xs text-indigo-600 font-bold mt-1 uppercase tracking-wider">{{ $selectedLead->company_name ?: 'Private Prospect' }}</div>
                                    </div>
                                    <div class="text-right">
                                        @php
                                            $stageNames = [
                                                'new' => 'New Lead', 'contacted' => 'Contacted',
                                                'proposal' => 'Proposal Sent', 'negotiation' => 'Negotiating',
                                                'won' => 'Won 🎉', 'lost' => 'Lost'
                                            ];
                                            $badgeClr = 'bg-gray-100 text-gray-700 border-gray-200';
                                            if ($selectedLead->pipeline_stage === 'won') $badgeClr = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                            elseif ($selectedLead->pipeline_stage === 'lost') $badgeClr = 'bg-rose-50 text-rose-700 border-rose-100';
                                            elseif ($selectedLead->pipeline_stage === 'negotiation') $badgeClr = 'bg-amber-50 text-amber-700 border-amber-100';
                                            elseif ($selectedLead->pipeline_stage === 'proposal') $badgeClr = 'bg-indigo-50 text-indigo-700 border-indigo-100';
                                        @endphp
                                        <span class="px-3 py-1 rounded-full text-xs font-black uppercase border {{ $badgeClr }}">
                                            {{ $stageNames[$selectedLead->pipeline_stage] ?? $selectedLead->pipeline_stage }}
                                        </span>
                                        <div class="text-[10px] text-slate-400 mt-1.5 font-bold font-mono">Weight: {{ $selectedLead->deal_probability }}% Probability</div>
                                    </div>
                                </div>

                                <!-- Detail fields grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-semibold text-slate-600">
                                    <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] text-slate-450 uppercase tracking-wider block font-extrabold">Contact Representative</span>
                                        <span class="text-slate-800 text-sm font-black mt-0.5 block">{{ $selectedLead->contact_name }}</span>
                                    </div>
                                    <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] text-slate-450 uppercase tracking-wider block font-extrabold">Est. Deal Value</span>
                                        <span class="text-slate-800 text-sm font-black mt-0.5 block font-mono">{{ setting('currency_symbol', '$') }}{{ number_format($selectedLead->deal_value, 2) }}</span>
                                    </div>
                                    <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] text-slate-450 uppercase tracking-wider block font-extrabold">Email Address</span>
                                        <a href="mailto:{{ $selectedLead->email }}" class="text-indigo-600 text-sm font-bold mt-0.5 block hover:underline">{{ $selectedLead->email }}</a>
                                    </div>
                                    <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] text-slate-450 uppercase tracking-wider block font-extrabold">Phone Number</span>
                                        <span class="text-slate-800 text-sm mt-0.5 block font-mono">{{ $selectedLead->phone ?: '--' }}</span>
                                    </div>
                                    <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] text-slate-450 uppercase tracking-wider block font-extrabold">Lead Source / Origin</span>
                                        <span class="text-slate-800 text-xs font-bold mt-0.5 block">{{ $selectedLead->source ?: '--' }}</span>
                                    </div>
                                    <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] text-slate-450 uppercase tracking-wider block font-extrabold">Assigned Sales Rep</span>
                                        <span class="text-slate-800 text-xs font-bold mt-0.5 block">{{ $selectedLead->assignedUser->name ?? '-- Unassigned --' }}</span>
                                    </div>
                                </div>

                                <!-- Notes section -->
                                <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 text-xs">
                                    <h4 class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider mb-1">Deal Notes & SCM Specifications</h4>
                                    <p class="text-slate-700 leading-relaxed font-medium whitespace-pre-wrap">{{ $selectedLead->notes ?: 'No description notes provided for this lead.' }}</p>
                                </div>

                                <!-- Activities Timeline -->
                                <div class="space-y-3">
                                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <!-- Note icon -->
                                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                        Chronological Interactions Log
                                    </h4>
                                    
                                    <div class="space-y-3 max-h-[160px] overflow-y-auto pr-1">
                                        @forelse($selectedLead->activities as $act)
                                            @php
                                                $actIcons = ['call' => '📞', 'email' => '📧', 'meeting' => '🤝', 'note' => '📝'];
                                            @endphp
                                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex items-start gap-3 text-xs">
                                                <span class="text-lg">{{ $actIcons[$act->type] ?? '📝' }}</span>
                                                <div class="flex-1">
                                                    <div class="flex justify-between font-bold">
                                                        <span class="text-slate-800 capitalize">{{ $act->type }} interaction</span>
                                                        <span class="text-[10px] text-slate-400 font-mono">{{ date('M d, Y', strtotime($act->activity_date)) }}</span>
                                                    </div>
                                                    <p class="text-slate-600 mt-1 font-medium">{{ $act->description }}</p>
                                                    <div class="text-[9px] text-slate-400 mt-1 font-bold">Recorded by: {{ $act->user->name ?? 'System' }}</div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-center py-6 text-xs text-slate-400 italic">No historical activities or interactions logged for this lead. Click "Log" on pipeline card to log.</p>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Conversion & Delete Safeguard Row (No Print) -->
                                <div class="flex justify-between items-center border-t border-slate-100 pt-4 mt-6 no-print">
                                    <div class="flex items-center gap-2">
                                        @if(!$selectedLead->customer_id)
                                            <button type="button" wire:click="convertToCustomer({{ $selectedLead->id }})" class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition-colors">
                                                👤 Convert to Customer Profile
                                            </button>
                                        @endif
                                        @if($selectedLead->pipeline_stage === 'won')
                                            <button type="button" wire:click="convertToQuote({{ $selectedLead->id }})" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl transition-colors">
                                                💰 Create Draft Quotation
                                            </button>
                                        @endif
                                    </div>
                                    <button type="button" 
                                            onclick="confirm('Are you sure you want to permanently delete this lead prospect?') && @this.deleteLead({{ $selectedLead->id }})" 
                                            class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl transition-colors"
                                    >
                                        Delete Lead
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- IN-PLACE EDIT FORM -->
                            <form wire:submit.prevent="updateLeadDetails" class="space-y-4 text-xs font-semibold text-gray-700">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <x-input-label value="Deal Title / Opportunity *" />
                                        <x-text-input wire:model="editTitle" type="text" class="mt-1 block w-full text-xs" required />
                                        <x-input-error :messages="$errors->get('editTitle')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Prospect Company Name" />
                                        <x-text-input wire:model="editCompanyName" type="text" class="mt-1 block w-full text-xs" />
                                    </div>
                                    <div>
                                        <x-input-label value="Contact Person Name *" />
                                        <x-text-input wire:model="editContactName" type="text" class="mt-1 block w-full text-xs" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Contact Email *" />
                                        <x-text-input wire:model="editEmail" type="email" class="mt-1 block w-full text-xs" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Phone Number" />
                                        <x-text-input wire:model="editPhone" type="text" class="mt-1 block w-full text-xs" />
                                    </div>
                                    <div>
                                        <x-input-label value="Est. Deal Value ($) *" />
                                        <x-text-input wire:model="editDealValue" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Pipeline Stage *" />
                                        <select wire:model="editPipelineStage" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold" required>
                                            <option value="new">New Lead</option>
                                            <option value="contacted">Contacted</option>
                                            <option value="proposal">Proposal Sent</option>
                                            <option value="negotiation">Negotiation</option>
                                            <option value="won">Won &amp; Close Deal 🎉</option>
                                            <option value="lost">Lost</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label value="Deal Probability (0 - 100%) *" />
                                        <x-text-input wire:model="editDealProbability" type="number" min="0" max="100" class="mt-1 block w-full text-xs" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Assigned User" />
                                        <select wire:model="editAssignedUserId" class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-700 font-semibold">
                                            <option value="">-- Choose User --</option>
                                            @foreach($this->getUsersList() as $usr)
                                                <option value="{{ $usr->id }}">{{ $usr->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label value="Source / Origin" />
                                        <x-text-input wire:model="editSource" type="text" class="mt-1 block w-full text-xs" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="Deal Notes" />
                                    <textarea wire:model="editNotes" rows="3" class="mt-1 block w-full rounded-xl border-gray-300 text-xs"></textarea>
                                </div>

                                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-150 no-print">
                                    <button type="button" wire:click="$set('isEditingDetails', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow">Save Changes</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

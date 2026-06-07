<?php

use function Livewire\Volt\{state, mount, with};
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;

state([
    'search' => '',
    'dateFrom' => '',
    'dateTo' => '',
]);

mount(function () {
    if (!Auth::user()->can('view general_ledger') && !Auth::user()->can('view receivables') && !Auth::user()->can('view payables')) {
        abort(403);
    }
    // Initialize standard accounts just in case
    \App\Helpers\AccountingJournalHelper::ensureAccountsExist();
});

with(function () {
    $query = JournalEntry::with(['lines.account']);

    if (trim($this->search)) {
        $query->where(function ($q) {
            $q->where('entry_number', 'like', '%' . $this->search . '%')
              ->orWhere('reference_source', 'like', '%' . $this->search . '%')
              ->orWhere('description', 'like', '%' . $this->search . '%');
        });
    }

    if ($this->dateFrom) {
        $query->where('posting_date', '>=', $this->dateFrom);
    }

    if ($this->dateTo) {
        $query->where('posting_date', '<=', $this->dateTo);
    }

    $entries = $query->latest('posting_date')->latest('id')->paginate(10);

    // Totals calculations
    $debitTotal = JournalLine::sum('debit_amount');
    $creditTotal = JournalLine::sum('credit_amount');
    $isBalanced = abs($debitTotal - $creditTotal) < 0.01;

    return [
        'entries' => $entries,
        'debitTotal' => $debitTotal,
        'creditTotal' => $creditTotal,
        'isBalanced' => $isBalanced,
    ];
});

?>

<div class="p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">General Ledger &amp; Journal Entries</h1>
                <p class="text-sm text-slate-500 mt-1">Audit double-entry balanced journal entries automatically posted from warehouse receipt, delivery, invoicing, and payment events.</p>
            </div>
            
            <div class="flex gap-2">
                <a href="{{ url('/finance/accounts') }}" class="bg-indigo-600 hover:bg-indigo-750 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow-md hover:shadow-lg transition duration-200 flex items-center gap-2 border border-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Chart of Accounts
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11V5a2 2 0 00-2-2H4a2 2 0 00-2 2v6a13 13 0 002.243 7.624l.02.03m10.802-1.2m3.44-2.04l-.053-.09A13.916 13.916 0 0015 11V5a2 2 0 022-2h3a2 2 0 022 2v6a13 13 0 02-2.243 7.624l-.02.03M15.5 8h.01M9.5 8h.01" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Ledger Debits</span>
                        <div class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ setting('currency_symbol', '$') }}{{ number_format($debitTotal, 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center text-violet-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11V5a2 2 0 00-2-2H4a2 2 0 00-2 2v6a13 13 0 002.243 7.624l.02.03m10.802-1.2m3.44-2.04l-.053-.09A13.916 13.916 0 0015 11V5a2 2 0 022-2h3a2 2 0 022 2v6a13 13 0 02-2.243 7.624l-.02.03M15.5 8h.01M9.5 8h.01" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Ledger Credits</span>
                        <div class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ setting('currency_symbol', '$') }}{{ number_format($creditTotal, 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-3">
                    @if($isBalanced)
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Trial Balance Status</span>
                            <div class="text-base font-black text-emerald-700 mt-1 uppercase tracking-wide flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Perfectly Balanced
                            </div>
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Trial Balance Status</span>
                            <div class="text-base font-black text-rose-700 mt-1 uppercase tracking-wide">
                                Out of Balance
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search entry number, description..." class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-400 font-bold uppercase">From</label>
                    <input wire:model.live="dateFrom" type="date" class="px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-400 font-bold uppercase">To</label>
                    <input wire:model.live="dateTo" type="date" class="px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>

                @if($search || $dateFrom || $dateTo)
                    <button wire:click="$set('search', ''); $set('dateFrom', ''); $set('dateTo', '');" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 rounded-xl transition">Clear</button>
                @endif
            </div>
        </div>

        <!-- Journal Entries List -->
        <div class="space-y-4">
            @forelse($entries as $entry)
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <!-- Header of Journal Entry -->
                    <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-extrabold text-slate-800">{{ $entry->entry_number }}</span>
                                <span class="text-xs px-2.5 py-0.5 font-bold rounded bg-indigo-50 text-indigo-700 uppercase">{{ $entry->reference_source }}</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 font-semibold">{{ $entry->description }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-slate-400 font-mono">{{ $entry->posting_date->format('Y-m-d') }}</span>
                        </div>
                    </div>
                    
                    <!-- Lines table of Journal Entry -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50/20 text-slate-400 uppercase tracking-widest text-[9px] font-bold border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-2.5 text-left">Account</th>
                                    <th class="px-6 py-2.5 text-right w-36">Debit</th>
                                    <th class="px-6 py-2.5 text-right w-36">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($entry->lines as $line)
                                    <tr class="hover:bg-slate-50/20">
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-slate-500 bg-slate-50 px-2 py-0.5 rounded border border-slate-200/50">{{ $line->account->code }}</span>
                                                <span class="font-semibold text-slate-750">{{ $line->account->name }}</span>
                                                <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold ml-1">({{ $line->account->account_type }})</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono font-bold text-slate-800">
                                            {{ $line->debit_amount > 0 ? setting('currency_symbol', '$') . number_format($line->debit_amount, 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono font-bold text-slate-800">
                                            {{ $line->credit_amount > 0 ? setting('currency_symbol', '$') . number_format($line->credit_amount, 2) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-12 text-center text-slate-500">
                    No journal entries logged in general ledger.
                </div>
            @endforelse

            <div class="mt-4">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</div>

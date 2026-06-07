<?php

use function Livewire\Volt\{state, mount, with};
use App\Models\Account;
use Illuminate\Support\Facades\Auth;

state([
    'search' => '',
    'typeFilter' => '',
]);

mount(function () {
    if (!Auth::user()->can('view general_ledger') && !Auth::user()->can('view receivables') && !Auth::user()->can('view payables')) {
        abort(403);
    }
    \App\Helpers\AccountingJournalHelper::ensureAccountsExist();
});

with(function () {
    $query = Account::query();

    if (trim($this->search)) {
        $query->where(function ($q) {
            $q->where('code', 'like', '%' . $this->search . '%')
              ->orWhere('name', 'like', '%' . $this->search . '%');
        });
    }

    if ($this->typeFilter) {
        $query->where('account_type', $this->typeFilter);
    }

    $accounts = $query->orderBy('code')->get();

    // Aggregations
    $assetBalance = 0;
    $liabilityBalance = 0;
    $equityBalance = 0;
    $revenueBalance = 0;
    $expenseBalance = 0;

    foreach ($accounts as $acc) {
        $bal = $acc->balance;
        switch ($acc->account_type) {
            case 'asset':
                $assetBalance += $bal;
                break;
            case 'liability':
                $liabilityBalance += $bal;
                break;
            case 'equity':
                $equityBalance += $bal;
                break;
            case 'revenue':
                $revenueBalance += $bal;
                break;
            case 'expense':
                $expenseBalance += $bal;
                break;
        }
    }

    return [
        'accounts' => $accounts,
        'assetBalance' => $assetBalance,
        'liabilityBalance' => $liabilityBalance,
        'equityBalance' => $equityBalance,
        'revenueBalance' => $revenueBalance,
        'expenseBalance' => $expenseBalance,
    ];
});

?>

<div class="p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Chart of Accounts</h1>
                <p class="text-sm text-slate-500 mt-1">Manage and audit double-entry ledger accounts and current net balances.</p>
            </div>
            
            <div class="flex gap-2">
                <a href="{{ url('/finance/ledger') }}" class="bg-indigo-600 hover:bg-indigo-750 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow-md hover:shadow-lg transition duration-200 flex items-center gap-2 border border-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    View General Ledger
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-5">
            <!-- Assets -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Assets</span>
                <div class="text-xl font-extrabold text-slate-800 mt-1.5">{{ setting('currency_symbol', '$') }}{{ number_format($assetBalance, 2) }}</div>
            </div>
            <!-- Liabilities -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Liabilities</span>
                <div class="text-xl font-extrabold text-slate-800 mt-1.5">{{ setting('currency_symbol', '$') }}{{ number_format($liabilityBalance, 2) }}</div>
            </div>
            <!-- Equity -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Equity</span>
                <div class="text-xl font-extrabold text-slate-800 mt-1.5">{{ setting('currency_symbol', '$') }}{{ number_format($equityBalance, 2) }}</div>
            </div>
            <!-- Revenue -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Revenue</span>
                <div class="text-xl font-extrabold text-slate-800 mt-1.5">{{ setting('currency_symbol', '$') }}{{ number_format($revenueBalance, 2) }}</div>
            </div>
            <!-- Expenses -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Expenses</span>
                <div class="text-xl font-extrabold text-slate-800 mt-1.5">{{ setting('currency_symbol', '$') }}{{ number_format($expenseBalance, 2) }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search accounts by code or name..." class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <select wire:model.live="typeFilter" class="w-full sm:w-44 px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
                    <option value="">All Types</option>
                    <option value="asset">Asset</option>
                    <option value="liability">Liability</option>
                    <option value="equity">Equity</option>
                    <option value="revenue">Revenue</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
        </div>

        <!-- Accounts Grid List -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-655">Code</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-655">Account Name</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-655">Account Type</th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-655">Status</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-655">Current Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($accounts as $account)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-mono font-bold text-slate-600">{{ $account->code }}</td>
                                <td class="px-6 py-4 font-bold text-slate-850">{{ $account->name }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                                        {{ $account->account_type === 'asset' ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' :
                                           ($account->account_type === 'liability' ? 'bg-amber-50 text-amber-700 border border-amber-100' :
                                           ($account->account_type === 'equity' ? 'bg-slate-50 text-slate-700 border border-slate-100' :
                                           ($account->account_type === 'revenue' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' :
                                            'bg-rose-50 text-rose-700 border border-rose-100'))) }}">
                                        {{ $account->account_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($account->is_active)
                                        <span class="text-xs font-bold text-emerald-600 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Active
                                        </span>
                                    @else
                                        <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 bg-slate-300 rounded-full"></span> Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-black text-slate-800">
                                    {{ setting('currency_symbol', '$') }}{{ number_format($account->balance, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">No ledger accounts registered.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

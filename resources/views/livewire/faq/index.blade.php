<?php

use Livewire\Volt\Component;

new class extends Component {
    public $activeTab = 'manual'; // 'manual' or 'architecture'
    public $selectedModule = 1;   // 1 to 10
};
?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 space-y-6 font-sans">
    
    <!-- Hero Header -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-black p-8 text-white shadow-xl border border-white/10">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="max-w-2xl">
                <h1 class="text-3xl md:text-4xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-white via-indigo-100 to-indigo-300">SCM ERP Documentation Hub</h1>
                <p class="mt-2 text-sm text-indigo-100/75 leading-relaxed font-medium">
                    Access comprehensive guides for system operators and explore the underlying technical architecture rules.
                </p>
            </div>
            
            <!-- Tab Switcher -->
            <div class="bg-white/10 p-1.5 rounded-2xl flex border border-white/10 shrink-0">
                <button wire:click="$set('activeTab', 'manual')" class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'manual' ? 'bg-white text-indigo-950 shadow-md' : 'text-white/80 hover:text-white hover:bg-white/5' }}">
                    Operator's User Manual
                </button>
                <button wire:click="$set('activeTab', 'architecture')" class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'architecture' ? 'bg-white text-indigo-950 shadow-md' : 'text-white/80 hover:text-white hover:bg-white/5' }}">
                    Architecture &amp; Lifecycle
                </button>
            </div>
        </div>
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-indigo-500 rounded-full blur-3xl opacity-20 pointer-events-none"></div>
    </div>

    @if($activeTab === 'manual')
        <!-- OPERATOR USER MANUAL VIEW -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
            
            <!-- Left Navigation Menu -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-1 lg:col-span-1">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 mb-3">Manual Chapters</div>
                
                @php
                    $modules = [
                        1 => 'Overview & Dashboard',
                        2 => 'CRM Leads & Quotes',
                        3 => 'Sales & Returns',
                        4 => 'WMS & Inventory',
                        5 => 'Procurement (RFQs/POs/GRN)',
                        6 => 'Manufacturing (BOM/MO)',
                        7 => 'Quality Assurance (QC)',
                        8 => 'Fleet & Shipments',
                        9 => 'General Ledger Accounting',
                        10 => 'AI Demand Forecasting',
                    ];
                @endphp

                @foreach($modules as $num => $title)
                    <button wire:click="$set('selectedModule', {{ $num }})" class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-semibold tracking-tight transition flex items-center gap-2.5 {{ $selectedModule === $num ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                        <span class="w-5 h-5 rounded-lg flex items-center justify-center text-[10px] font-bold {{ $selectedModule === $num ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                            {{ $num }}
                        </span>
                        {{ $title }}
                    </button>
                @endforeach

                <!-- Download Card -->
                <div class="mt-6 pt-6 border-t border-slate-100 text-center space-y-3 px-2">
                    <div class="text-xs font-bold text-slate-700">Offline Manuals</div>
                    <p class="text-[10px] text-slate-400">Download simple manuals to read on your device or print.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('pdf.manual') }}" target="_blank" class="px-2 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-lg text-[10px] flex items-center justify-center gap-1 transition">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            PDF
                        </a>
                        <a href="{{ asset('user_manual.docx') }}" download class="px-2 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[10px] flex items-center justify-center gap-1 transition">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            DOCX
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Content Panel -->
            <div class="bg-white p-8 rounded-3xl border border-slate-200/80 shadow-sm lg:col-span-3 min-h-[500px] flex flex-col justify-between">
                <div>
                    @if($selectedModule === 1)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 1: System Overview &amp; Dashboard
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Welcome to SCM ERP! The Dashboard is your primary workspace window and control room. It consolidates metrics from all other business modules in real-time, allowing operators to monitor system health at a glance.
                            </p>
                            
                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Dashboard Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Finance KPIs</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">$150K Cash Balance</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Fleet Status</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">V-100 Delivery Van</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Stock Alerts</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Critical Reorders</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Log into the system to load the main dashboard.</li>
                                <li>Scan the top numerical tiles to check overall performance.</li>
                                <li>Check the warnings panel for critical tasks (e.g. low inventory items).</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Metric Category</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Real-Life Parameter</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Current Value</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Status Indicator</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">Cash &amp; Liquidity</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Primary Bank Reserves</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$150,000.00</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-green-600">Healthy</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">Accounts Receivable</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Outstanding Customer Invoices</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$29,500.00</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-amber-600">Pending Payment</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">Accounts Payable</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Outstanding Supplier Bills</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$3,000.00</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-slate-500">Due in 15 Days</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">Low Stock Warning</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Raw Material RM-STL-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">2 Units</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-red-600">Critical Alert (Safety: 5)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 2)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 2: CRM Leads &amp; Quotations
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                The Customer Relationship Management (CRM) module tracks prospective client conversations and converts pricing quotes to confirmed sales contracts. In our real-life scenario, TechVenture Solutions Inc. inquires about purchasing high-grade Enterprise Server Racks.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">CRM Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">CRM Lead</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">TechVenture Lead</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Draft Quote</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">QT-2026-001 ($25k)</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Confirmed SO</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">SO-2026-001 (Locked)</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Open 'CRM Leads' from the sidebar and click 'Add Lead'. Fill in client details for TechVenture Solutions Inc.</li>
                                <li>Create a Quotation from the lead card, selecting the target product 'Enterprise Server Racks' (SKU: FG-SRV-42U).</li>
                                <li>Set price, tax rate (GST 18%), and save. Once approved by the customer, click 'Convert to Sales Order'.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lead Reference</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Prospect Customer</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Target SKU</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Qty Request</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Quoted Price</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Tax (18%)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">LD-2026-081</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">TechVenture Solutions Inc.</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">FG-SRV-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">10 Units</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$2,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$4,500.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 3)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 3: Sales &amp; Customer Management
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Oversee customer directory records, Sales Orders (SO), invoice billing, and customer product return requests (RMA). Converting the quote creates Sales Order SO-2026-001, which locks the inventory allocation.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Sales &amp; Return Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Sales Order</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">SO-2026-001 processing</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Generate Inv</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">INV-2026-001 issued</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Receive Cash</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Debit Bank JV-1050</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Navigate to Sales -> Sales Orders. Check that SO-2026-001 is generated in draft.</li>
                                <li>Verify TechVenture Solutions Inc.'s shipping address and update the order status to 'processing'.</li>
                                <li>Click 'Generate Invoice' to issue invoice INV-2026-001. Mark as paid when payments arrive.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Order ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Invoice ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Client Name</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Billable</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Payment Status</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">RMA Log</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">SO-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">INV-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">TechVenture Solutions</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$29,500.00</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-amber-600">Unpaid (Pending)</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">RMA-2026-001 (Bezel)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 4)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 4: WMS &amp; Inventory Management
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                The Warehouse Management System (WMS) tracks real-time quantities of goods located inside physical bins, racks, zones, and warehouses. Items are organized in a strict logical tree to allow operators to find stock instantly.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">WMS Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">WMS Tree Layout</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Warehouse &rarr; Zone &rarr; Bin</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Stock Take Audit</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Verification Count</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Reconcile Variance</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Post Discrepancies</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Open 'Inventory' -> 'Warehouses'. Review locations: Warehouse WH-01, Zone Z-RAW, Rack R-03, Bin A02-R2-S1.</li>
                                <li>Use internal transfer to move components from receiving to assembly bins.</li>
                                <li>Perform a physical Stock Take. If a discrepancy is found, submit counts to auto-adjust.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Warehouse ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Zone Name</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Rack ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Bin Address</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">SKU Stored</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">System Stock</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Audit Variance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">WH-01 (Central)</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Z-RAW (Raw Materials)</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">R-03</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">A02-R2-S1</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">RM-STL-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">12 Units</td>
                                            <td class="px-4 py-3 text-xs text-red-600 font-bold">-2 (Adjusted)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 5)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 5: Procurement (RFQs, POs, &amp; GRN)
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Procurement manages supplier listings, bid tracking (RFQs), purchase orders (PO), and goods receiving (GRN). This initiates the flow by securing the raw steel frames required to assemble the server racks.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Procurement Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Supplier Bid Award</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">RFQ-2026-001 approved</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Issue Order</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">PO-2026-001 ($3,000)</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Goods Receipt</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">GRN-2026-001 received</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Create Supplier RFQ RFQ-2026-001. Send to Global Iron &amp; Steel Co.</li>
                                <li>Accept their quote and generate PO-2026-001 for 10 units of RM-STL-42U.</li>
                                <li>When delivery arrives, click 'Receive Goods' to create GRN-2026-001, moving stock to bin A02-R2-S1.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">RFQ Reference</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">PO Reference</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Awarded Supplier</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">SKU Ordered</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Qty Received</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">GRN ID</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">RFQ-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">PO-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Global Iron &amp; Steel Co.</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">RM-STL-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">10 Units</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">GRN-2026-001</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 6)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 6: Manufacturing (BOM &amp; MO)
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Set up product recipes (Bills of Materials) and execute Manufacturing Orders (MO) to assemble raw components into finished items. Completed MOs auto-consume component items and add finished products to inventory.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Manufacturing Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Recipe Config</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">BOM-FG-42U components</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Production Release</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">MO-2026-001 active</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Fulfill Finished Item</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Register finished racks</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Open Manufacturing -> Bills of Materials (BOM). Create recipe for finished product FG-SRV-42U.</li>
                                <li>Go to Manufacturing Orders (MO). Click 'New Order', choose BOM and quantity 10, then confirm.</li>
                                <li>Progress the MO status to 'Produce'. Upon completion, select destination bin A02-R3-S2.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">BOM Recipe ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Manufacturing MO</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Finished SKU</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Target Qty</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Raw Material Debits</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Finished Goods Credits</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">BOM-FG-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">MO-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">FG-SRV-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">10 Units</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">10x RM-STL-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">10x FG-SRV-42U</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 7)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 7: Quality Control &amp; Assurance (QC)
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Prevents defective inventory from being shipped to customers or placed into active warehouse storage. All incoming items (GRN) and completed assembly runs (MO) trigger pending QC audits.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">QC Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Production Complete</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Trigger quality check</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Verify Standards</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Verify QC-2026-001 dimensions</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Audit Decision</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Release to Central WH</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Go to the Quality Audits dashboard. Locate the pending check triggered by MO-2026-001.</li>
                                <li>Click 'Audit Release'. Review inspection points (dimensions, rivets, grounding).</li>
                                <li>Register the verdict as 'Passed'. This releases the 10 units to active inventory.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Audit Reference</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Origin Document</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Audited SKU</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Inspector Name</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">QC Verdict</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Reconciled Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">QC-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">MO-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">FG-SRV-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Alice Smith</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-green-600">Passed (Released)</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 8)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 8: Fleet, Shipments, &amp; Logistics
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Coordinates transport operations, vehicle profiles, fuel tracking, and driver dispatch routines. The logistics engine manages shipment schedules and posts ledger transactions upon completion.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Logistics Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Allocate Delivery</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">SH-2026-001 Scheduled</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Dispatch Vehicle</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Ford Transit Assigned</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Mark Delivered</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Trigger COGS ledgers</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Open Logistics -> Dispatch Board. Check pending sales shipment SH-2026-001.</li>
                                <li>Drag and assign the shipment to Driver John Doe and Delivery Van V-100.</li>
                                <li>Mark shipment as 'Delivered' upon client arrival to trigger COGS ledger entries.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Shipment ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Driver Name</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Vehicle ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Destination Route</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Delivery Status</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">COGS Debit</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">SH-2026-001</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">John Doe</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">V-100 (Ford Transit)</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">WH-01 to TechVenture HQ</td>
                                            <td class="px-4 py-3 text-[10px] font-bold text-green-600">Delivered</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">$6,500.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 9)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 9: Financial Accounting &amp; Chart of Accounts
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Provides compliance-ready ledger reporting. SCM ERP automatically generates standard double-entry journal postings as business actions occur, ensuring financial integrity.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">General Ledger Posting Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Invoice Post</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Debit Accounts Receivable</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Payment Receipt</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Credit Accounts Receivable</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">COGS Deduction</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Release shipment stock</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Navigate to Finance -> General Ledger to view the double-entry transactions log.</li>
                                <li>Open Chart of Accounts. Observe assets incremented and liabilities cleared.</li>
                                <li>Use filters to review balance sheets and verify that debits equal credits.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Journal ID</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Posting Date</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Account Name</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Debit Amount</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Credit Amount</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Transaction Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1049</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">Accounts Receivable</td>
                                            <td class="px-4 py-3 text-xs text-green-600 font-bold">$29,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Invoice INV-2026-001 TechVenture</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1049</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">Sales Revenue</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-red-600 font-bold">$25,000.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Invoice INV-2026-001 TechVenture</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1049</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">GST Liabilities</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-red-600 font-bold">$4,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Invoice INV-2026-001 TechVenture</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1050</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">Cash &amp; Bank</td>
                                            <td class="px-4 py-3 text-xs text-green-600 font-bold">$29,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Payment received from TechVenture</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1050</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">Accounts Receivable</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-red-600 font-bold">$29,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Payment received from TechVenture</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1051</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">Cost of Goods Sold</td>
                                            <td class="px-4 py-3 text-xs text-green-600 font-bold">$6,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">COGS for shipment SH-2026-001</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">JV-2026-1051</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">2026-06-07</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">Finished Goods Inventory</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">$0.00</td>
                                            <td class="px-4 py-3 text-xs text-red-600 font-bold">$6,500.00</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">Inventory deduction for SH-2026-001</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($selectedModule === 10)
                        <div class="space-y-4">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-indigo-600 rounded-full"></span>
                                Chapter 10: Enterprise AI &amp; Demand Forecasting
                            </h2>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Optimize stock levels and prevent cash flow blocks with automated predictive demand algorithms. By calculating daily average usage, the forecasting module alerts staff and generates replenishment suggestions.
                            </p>

                            <!-- Flowchart -->
                            <div class="my-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <div class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-4">Forecasting Process Flow:</div>
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Velocity Analysis</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">1.2 units/day velocity</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Safety Adjustment</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">130% Multiplier slider</div>
                                    </div>
                                    <div class="shrink-0 text-slate-400 rotate-90 sm:rotate-0">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 flex-1 w-full text-center">
                                        <div class="font-bold text-slate-800 text-xs">Purchase Drafts</div>
                                        <div class="text-[10px] text-slate-500 mt-1 font-medium">Auto-generate PO-2026-002</div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="font-bold text-slate-800 mt-6">Step-by-Step Operator Guide:</h3>
                            <ul class="list-disc list-inside text-xs text-slate-600 space-y-2 pl-2">
                                <li>Open AI Forecasting. Review consumption velocity calculations.</li>
                                <li>Adjust safety stock multiplier (e.g. 130%) to simulate peak seasons.</li>
                                <li>Click 'Apply Safety Settings' and then 'Bulk Auto-Generate POs' to create drafts.</li>
                            </ul>

                            <h3 class="font-bold text-slate-800 mt-6">Real-Life Transactional Example (TechVenture Solutions Inc.):</h3>
                            <div class="overflow-x-auto mt-2">
                                <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Product SKU</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Avg Daily Velocity</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lead Time (Days)</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Safety Multiplier</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Current Stock Level</th>
                                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Replenish PO</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">RM-STL-42U</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">1.2 Units</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">3 Days</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">130% (Seasonality)</td>
                                            <td class="px-4 py-3 text-xs text-red-600 font-bold">2 Units (Alert)</td>
                                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">PO-2026-002 (Draft)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-between items-center pt-6 border-t border-slate-100 text-xs text-slate-400 mt-8">
                    <span>SCM ERP Simplified Manual &copy; 2026</span>
                    <span>Page {{ $selectedModule }} of 10</span>
                </div>
            </div>

        </div>
    @else
        <!-- ARCHITECTURE & LIFECYCLE VIEW -->
        <div class="space-y-6">
            
            <!-- Core Lifecycle Graphic -->
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-200 overflow-hidden relative">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-xl font-bold text-slate-800 tracking-tight flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        The Supply Chain Lifecycle
                    </h3>
                </div>
                
                <div class="flex flex-col md:flex-row items-start justify-between relative gap-6 md:gap-4 w-full">
                    <!-- Connecting Line -->
                    <div class="hidden md:block absolute top-8 left-12 right-12 h-0.5 bg-gradient-to-r from-rose-200 via-indigo-200 to-emerald-200 -z-10"></div>

                    @php
                        $steps = [
                            ['icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'title' => 'Procure', 'desc' => 'Issue POs to suppliers.', 'color' => 'rose'],
                            ['icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'title' => 'Receive', 'desc' => 'GRN logs physical stock.', 'color' => 'amber'],
                            ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Commit', 'desc' => 'Sales Orders reserve stock.', 'color' => 'blue'],
                            ['icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'title' => 'Fulfill', 'desc' => 'Shipments deduct inventory.', 'color' => 'indigo'],
                            ['icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Reconcile', 'desc' => 'Invoices generate revenue.', 'color' => 'emerald'],
                        ];
                    @endphp

                    @foreach($steps as $index => $step)
                    <div class="flex flex-col items-center text-center relative w-full md:flex-1 group cursor-default">
                        <div class="w-16 h-16 bg-white rounded-2xl border-2 border-slate-100 shadow-sm flex items-center justify-center mb-4 group-hover:-translate-y-1 group-hover:shadow-md transition-all duration-300 relative z-10">
                            <svg class="w-7 h-7 text-{{ $step['color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $step['icon'] }}"></path></svg>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-3 w-full border border-slate-100 group-hover:bg-white group-hover:border-slate-200 transition-colors">
                            <h4 class="font-bold text-slate-800 text-sm tracking-tight">{{ $index + 1 }}. {{ $step['title'] }}</h4>
                            <p class="text-[11px] text-slate-500 mt-1.5 leading-snug font-medium">{{ $step['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Detailed Documentation Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column: Master Data & Inventory -->
                <div class="space-y-8 lg:col-span-1">
                    
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                            <span class="w-1.5 h-6 rounded-full bg-slate-800"></span> Master Hubs
                        </h3>
                        <div class="space-y-4 relative z-10">
                            <div class="border-l-2 border-slate-200 pl-4">
                                <h4 class="text-sm font-bold text-slate-700">Products Catalog</h4>
                                <p class="text-xs text-slate-500 mt-1 leading-relaxed">The atomic unit of the system. SKUs bind GRNs, Shipments, and Invoices together.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                            <span class="w-1.5 h-6 rounded-full bg-emerald-500"></span> Inventory Architecture
                        </h3>
                        <div class="space-y-4 relative z-10">
                            <div class="bg-emerald-50/50 rounded-xl p-3 border border-emerald-100/50 text-xs text-emerald-800 font-medium leading-relaxed">
                                Warehouses are strictly hierarchical: <br>
                                <strong>Warehouse &rarr; Zone &rarr; Rack &rarr; Bin</strong>
                            </div>
                            <div class="border-l-2 border-emerald-200 pl-4">
                                <h4 class="text-sm font-bold text-slate-700">Stock Integrity</h4>
                                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Physical stock is immutable. It only shifts via GRNs (In), Shipments (Out), Transfers (Internal), or explicit Adjustments.</p>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <!-- Right Column: Operational Flows -->
                <div class="space-y-6 lg:col-span-2">
                    
                    <div class="bg-white rounded-3xl p-1 shadow-sm border border-slate-200">
                        <details class="group [&_summary::-webkit-details-marker]:hidden" open>
                            <summary class="flex items-center justify-between p-5 cursor-pointer bg-slate-50 rounded-[22px] hover:bg-slate-100 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-800">Inbound Operations (Procure to Pay)</h3>
                                </div>
                                <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="p-6 pt-4 text-sm text-slate-600 space-y-5">
                                <div class="flex items-start gap-4 group/item">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 group-hover/item:bg-rose-100 group-hover/item:text-rose-600 transition-colors">1</div>
                                    <div>
                                        <strong class="text-slate-800 block mb-1">Purchase Orders (PO)</strong>
                                        <p class="leading-relaxed text-xs">The commercial intent to buy. Issued to a supplier. Does not affect physical stock or ledgers until approved and received.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4 group/item">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 group-hover/item:bg-rose-100 group-hover/item:text-rose-600 transition-colors">2</div>
                                    <div>
                                        <strong class="text-slate-800 block mb-1">Goods Receipt Notes (GRN)</strong>
                                        <p class="leading-relaxed text-xs">The operational execution. Validates the physical arrival of PO items and writes directly to the warehouse bins.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4 group/item">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 group-hover/item:bg-rose-100 group-hover/item:text-rose-600 transition-colors">3</div>
                                    <div>
                                        <strong class="text-slate-800 block mb-1">Accounts Payable (Expenses)</strong>
                                        <p class="leading-relaxed text-xs">The financial execution. PO amounts and taxes are registered in the ledger to track outgoing liabilities.</p>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div class="bg-white rounded-3xl p-1 shadow-sm border border-slate-200">
                        <details class="group [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex items-center justify-between p-5 cursor-pointer bg-slate-50 rounded-[22px] hover:bg-slate-100 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-800">Outbound Operations (Order to Cash)</h3>
                                </div>
                                <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="p-6 pt-4 text-sm text-slate-600 space-y-5">
                                <div class="flex items-start gap-4 group/item">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 group-hover/item:bg-indigo-100 group-hover/item:text-indigo-600 transition-colors">1</div>
                                    <div>
                                        <strong class="text-slate-800 block mb-1">Quotations &amp; Sales Orders</strong>
                                        <p class="leading-relaxed text-xs">Quotations offer pricing. Once accepted, they convert to Sales Orders, which act as a firm contract and reserve stock.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4 group/item">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 group-hover/item:bg-indigo-100 group-hover/item:text-indigo-600 transition-colors">2</div>
                                    <div>
                                        <strong class="text-slate-800 block mb-1">Logistics &amp; Shipments</strong>
                                        <p class="leading-relaxed text-xs">Dispatching goods based on SOs. Processing a shipment physically deducts the stock from the warehouse bins.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4 group/item">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 group-hover/item:bg-indigo-100 group-hover/item:text-indigo-600 transition-colors">3</div>
                                    <div>
                                        <strong class="text-slate-800 block mb-1">Accounts Receivable (Invoices)</strong>
                                        <p class="leading-relaxed text-xs">Billing the customer. Invoices can be generated directly from SOs or manually created for unlinked revenue streams.</p>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>

                    <!-- Reporting Section -->
                    <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden shadow-lg border border-slate-800">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-violet-600 rounded-full blur-3xl opacity-30 -mr-20 -mt-20 pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-white">
                                <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Financial Intelligence &amp; Reporting
                            </h3>
                            <p class="text-sm text-slate-300 leading-relaxed mb-6 font-medium">
                                Every action described above generates telemetry. The Reporting Engine aggregates this data to provide compliance-ready sheets.
                            </p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="bg-white/5 hover:bg-white/10 transition-colors border border-white/10 hover:border-white/20 rounded-xl px-4 py-3 flex flex-col items-center justify-center gap-1 cursor-default">
                                    <span class="text-xs font-bold text-slate-200">Tax Returns</span>
                                </div>
                                <div class="bg-white/5 hover:bg-white/10 transition-colors border border-white/10 hover:border-white/20 rounded-xl px-4 py-3 flex flex-col items-center justify-center gap-1 cursor-default">
                                    <span class="text-xs font-bold text-slate-200">GST/VAT</span>
                                </div>
                                <div class="bg-white/5 hover:bg-white/10 transition-colors border border-white/10 hover:border-white/20 rounded-xl px-4 py-3 flex flex-col items-center justify-center gap-1 cursor-default">
                                    <span class="text-xs font-bold text-slate-200">PO Analysis</span>
                                </div>
                                <div class="bg-white/5 hover:bg-white/10 transition-colors border border-white/10 hover:border-white/20 rounded-xl px-4 py-3 flex flex-col items-center justify-center gap-1 cursor-default">
                                    <span class="text-xs font-bold text-slate-200">Sales Volume</span>
                                </div>
                                <div class="bg-white/5 hover:bg-white/10 transition-colors border border-white/10 hover:border-white/20 rounded-xl px-4 py-3 flex flex-col items-center justify-center gap-1 cursor-default">
                                    <span class="text-xs font-bold text-slate-200">Inventory Valuation</span>
                                </div>
                                <div class="bg-white/5 hover:bg-white/10 transition-colors border border-white/10 hover:border-white/20 rounded-xl px-4 py-3 flex flex-col items-center justify-center gap-1 cursor-default">
                                    <span class="text-xs font-bold text-slate-200">GRN Audit Trail</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Future Roadmap -->
            <div class="mt-12 bg-white rounded-3xl p-8 shadow-sm border border-slate-200">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-purple-500 to-indigo-500 text-white flex items-center justify-center shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-2xl font-black text-slate-800">Advanced Intelligence &amp; Future Roadmap</h3>
                </div>
                <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                    The SCM ERP system is designed to scale. Here are some advanced and complex features that can be integrated to elevate the software to an enterprise-grade AI-driven platform:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-shadow">
                        <h4 class="font-bold text-indigo-700 mb-2 text-lg">1. Predictive AI Demand Forecasting</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Integrate machine learning models (e.g., ARIMA, Prophet, or Deep Learning) to analyze historical sales data, seasonality, and market trends. The system can automatically suggest reorder quantities and trigger POs before stockouts occur, optimizing working capital.
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-shadow">
                        <h4 class="font-bold text-indigo-700 mb-2 text-lg">2. Automated Supplier Bidding (Reverse Auctions)</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Transform the RFQ module into a dynamic portal where suppliers can log in and submit competitive bids in real-time. The system can automatically evaluate bids based on cost, lead time, and supplier rating, awarding the contract to the best candidate.
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-shadow">
                        <h4 class="font-bold text-indigo-700 mb-2 text-lg">3. OCR-Powered Invoice Processing</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Utilize Optical Character Recognition (OCR) and LLMs to automatically scan, extract, and digitize incoming supplier invoices. The system will auto-match invoice lines against existing POs and GRNs (3-way matching) and flag discrepancies.
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-shadow">
                        <h4 class="font-bold text-indigo-700 mb-2 text-lg">4. Advanced Route Optimization &amp; Fleet Telematics</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Enhance the Logistics module by integrating live GPS tracking and algorithmic route optimization (e.g., VRP solvers) to calculate the most fuel-efficient delivery paths for multiple shipments, factoring in real-time traffic and delivery windows.
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-shadow">
                        <h4 class="font-bold text-indigo-700 mb-2 text-lg">5. Multi-Echelon Inventory Optimization (MEIO)</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            For multi-warehouse setups, MEIO algorithmically determines where to position stock across the entire supply chain network to buffer against volatility, minimizing total holding costs while guaranteeing service levels.
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-shadow">
                        <h4 class="font-bold text-indigo-700 mb-2 text-lg">6. Dynamic Pricing Engine</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Implement automated pricing strategies that adjust Sales Order unit prices in real-time based on current stock velocity, competitor pricing APIs, and procurement cost fluctuations, ensuring margins are strictly protected.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    @endif
</div>

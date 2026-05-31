<?php

use Livewire\Volt\Component;

new class extends Component {
    // Volt component logic if needed
};
?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-10 space-y-8 font-sans">
    
    <!-- Hero Section -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900 via-slate-900 to-black p-10 text-white shadow-2xl border border-white/10">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
        <div class="relative z-10 max-w-2xl">
            <h1 class="text-4xl md:text-5xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-white to-indigo-200">System Intelligence Hub</h1>
            <p class="mt-4 text-base text-indigo-100/80 leading-relaxed font-medium">
                Comprehensive architecture documentation and module interconnectivity guide. Understand the entire lifecycle of data from procurement to final financial reconciliation.
            </p>
        </div>
        <!-- Decorative glowing orb -->
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-indigo-500 rounded-full blur-3xl opacity-20 pointer-events-none"></div>
    </div>

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

    <!-- Advanced Intelligence & Roadmap -->
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
                <h4 class="font-bold text-indigo-700 mb-2 text-lg">4. Advanced Route Optimization & Fleet Telematics</h4>
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

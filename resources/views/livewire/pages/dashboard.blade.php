<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\InventoryTransaction;
use App\Models\BinProductStock;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Expense;

new class extends Component {
    public $timeframe = '30_days';
    public $lowStockCount = 0;
    public $lowStockProducts = [];
    public $openPurchases = 0;
    public $openSales = 0;
    public $inTransitShipments = 0;
    public $recentTransactions = [];
    
    // Flow Data (DFD)
    public $supplyChainFlow = [];
    public $financialFlow = [];

    // Accounts Outstanding
    public $accountsReceivableUnpaid = 0;
    public $accountsPayableUnpaid = 0;

    // Financials
    public $totalRevenue = 0;
    public $totalSpend = 0;
    
    // Advanced Options
    public $chartDates = [];
    public $chartRevenue = [];
    public $chartSpend = [];
    public $topProductsLabels = [];
    public $topProductsSeries = [];
    public $aiInsights = [];
    public $grossProfit = 0;
    public $netMargin = 0;
    
    // New SCM Dash Metrics
    public $topProductsList = [];
    public $warehouseUtilization = 0;
    public $activeVehiclesCount = 0;
    public $totalVehiclesCount = 0;
    public $fleetUtilization = 0;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $start = now();
        $end = now();
        
        if ($this->timeframe === 'today') {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        } elseif ($this->timeframe === '7_days') {
            $start = now()->subDays(7)->startOfDay();
            $end = now()->endOfDay();
        } elseif ($this->timeframe === '30_days') {
            $start = now()->subDays(30)->startOfDay();
            $end = now()->endOfDay();
        } elseif ($this->timeframe === 'this_month') {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        } else {
            // all_time
            $start = now()->subYears(10);
            $end = now()->addYears(1);
        }

        // 1. KPI Cards data
        $this->totalRevenue = SalesOrder::whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $this->totalSpend = PurchaseOrder::whereIn('status', ['approved', 'partially_received', 'received'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount') + Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])->sum('amount');

        // Low Stock Items (where total quantity in bin_product_stock <= reorder_level)
        $products = Product::all();
        $lowStockCountList = [];
        $this->lowStockProducts = [];

        // Let's get stock sums for all products
        $stockSums = BinProductStock::groupBy('product_id')
            ->select('product_id', \DB::raw('SUM(quantity) as total_qty'))
            ->pluck('total_qty', 'product_id')
            ->toArray();

        foreach ($products as $product) {
            $qty = $stockSums[$product->id] ?? 0;
            if ($qty <= $product->reorder_level) {
                $lowStockCountList[] = $product->id;
                $this->lowStockProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category,
                    'reorder_level' => $product->reorder_level,
                    'current_stock' => $qty,
                ];
            }
        }
        $this->lowStockCount = count($lowStockCountList);
        $this->lowStockProducts = array_slice($this->lowStockProducts, 0, 5);

        $this->openPurchases = PurchaseOrder::whereIn('status', ['draft', 'approved', 'partially_received'])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $this->openSales = SalesOrder::whereIn('status', ['pending', 'processing', 'confirmed'])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $this->inTransitShipments = Shipment::where('status', 'In Transit')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $this->recentTransactions = InventoryTransaction::with(['product', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        // Flow Data (DFD) based on Timeframe
        $this->supplyChainFlow = [];
        $this->financialFlow = [];

        // 1. Supply Chain Flow (DFD)
        $poTotals = PurchaseOrder::select('supplier_id', \DB::raw('SUM(total_amount) as total'))
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->groupBy('supplier_id')
            ->with('supplier')->get();
        foreach($poTotals as $po) {
            $this->supplyChainFlow[] = ['Supplier: ' . ($po->supplier->name ?? 'Unknown'), 'Inventory', (float)$po->total];
        }
        
        $salesTotals = SalesOrder::select('customer_id', \DB::raw('SUM(total_amount) as total'))
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->groupBy('customer_id')
            ->with('customer')->get();
        foreach($salesTotals as $sale) {
            $this->supplyChainFlow[] = ['Inventory', 'Customer: ' . ($sale->customer->name ?? 'Unknown'), (float)$sale->total];
        }

        // 2. Financial Cash Flow (DFD)
        $totalRevenueFlow = \App\Models\PaymentLog::whereNotNull('account_receivable_id')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        if ($totalRevenueFlow > 0) {
            $this->financialFlow[] = ['Revenue (Sales)', 'Company Cash', (float)$totalRevenueFlow];
        } else {
            // Fallback if no payments recorded
            $this->financialFlow[] = ['Revenue (Sales)', 'Company Cash', (float)$this->totalRevenue];
        }

        $expensesByCategory = Expense::select('category', \DB::raw('SUM(amount) as total'))
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        foreach($expensesByCategory as $exp) {
            $this->financialFlow[] = ['Company Cash', 'Expense: ' . ($exp->category ?: 'Uncategorized'), (float)$exp->total];
        }

        $poPayments = \App\Models\PaymentLog::whereNotNull('account_payable_id')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        if ($poPayments > 0) {
            $this->financialFlow[] = ['Company Cash', 'Supplier Payments', (float)$poPayments];
        }

        // 5. Unpaid Finance totals
        $this->accountsReceivableUnpaid = AccountReceivable::where('status', 'unpaid')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
            
        $this->accountsPayableUnpaid = AccountPayable::where('status', 'unpaid')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        // ---- ADVANCED OPTIONS DATA ----
        
        // Charts Data: Dynamic by timeframe
        $this->chartDates = [];
        $this->chartRevenue = [];
        $this->chartSpend = [];
        
        $salesRecords = SalesOrder::whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->whereBetween('created_at', [$start, $end])
            ->get();
        $purchaseRecords = PurchaseOrder::whereIn('status', ['approved', 'partially_received', 'received'])
            ->whereBetween('created_at', [$start, $end])
            ->get();
        $expenseRecords = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])->get();
        
        $intervalDays = 1;
        $periods = 7;
        if ($this->timeframe === 'today') { $intervalDays = 0; $periods = 24; } // Hours
        elseif ($this->timeframe === '30_days' || $this->timeframe === 'this_month') { $intervalDays = 4; $periods = 7; }
        elseif ($this->timeframe === 'all_time') { $intervalDays = 365; $periods = 5; }
        
        for ($i = $periods; $i >= 0; $i--) {
            if ($this->timeframe === 'today') {
                $dStart = now()->subHours($i)->startOfHour();
                $dEnd = now()->subHours($i)->endOfHour();
                $label = $dStart->format('H:00');
            } else {
                $dStart = now()->subDays($i * $intervalDays)->startOfDay();
                // for all_time, it's roughly years. let's just use days for simplicity for now
                if ($this->timeframe === 'all_time') {
                     $dStart = now()->subYears($i)->startOfYear();
                     $dEnd = now()->subYears($i)->endOfYear();
                     $label = $dStart->format('Y');
                } else {
                    $dEnd = now()->subDays($i * $intervalDays)->addDays(max(0, $intervalDays - 1))->endOfDay();
                    $label = $dStart->format('M d');
                }
            }
            $this->chartDates[] = $label;
            
            $rev = $salesRecords->whereBetween('created_at', [$dStart, $dEnd])->sum('total_amount');
            $spend = $purchaseRecords->whereBetween('created_at', [$dStart, $dEnd])->sum('total_amount') + 
                     $expenseRecords->whereBetween('expense_date', [$dStart->toDateString(), $dEnd->toDateString()])->sum('amount');
                     
            $this->chartRevenue[] = (float)$rev;
            $this->chartSpend[] = (float)$spend;
        }
        
        // Top Products by Quantity Sold and Revenue
        $topProductsQuery = \DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.category',
                'products.unit_price',
                \DB::raw('SUM(sales_order_items.quantity) as total_sold'),
                \DB::raw('SUM(sales_order_items.quantity * sales_order_items.unit_price) as total_revenue')
            )
            ->whereBetween('sales_orders.created_at', [$start, $end])
            ->whereIn('sales_orders.status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.category', 'products.unit_price')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        $this->topProductsList = [];
        foreach ($topProductsQuery as $p) {
            $sold = (int)$p->total_sold;
            if ($sold >= 30) {
                $velocity = 'HIGH';
                $badgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-200';
            } elseif ($sold >= 10) {
                $velocity = 'MODERATE';
                $badgeColor = 'bg-blue-100 text-blue-800 border-blue-200';
            } else {
                $velocity = 'SLOW';
                $badgeColor = 'bg-slate-100 text-slate-800 border-slate-200';
            }

            $this->topProductsList[] = [
                'name' => $p->name,
                'sku' => $p->sku,
                'category' => $p->category,
                'total_sold' => $sold,
                'total_revenue' => (float)$p->total_revenue,
                'velocity' => $velocity,
                'badge_color' => $badgeColor
            ];
        }

        $this->topProductsLabels = $topProductsQuery->pluck('name')->toArray();
        $this->topProductsSeries = $topProductsQuery->pluck('total_sold')->map(fn($val) => (int)$val)->toArray();
        
        if (empty($this->topProductsLabels)) {
            $this->topProductsLabels = ['No Data'];
            $this->topProductsSeries = [1];
        }
        
        // Profitability
        $this->grossProfit = $this->totalRevenue - $this->totalSpend;
        $this->netMargin = $this->totalRevenue > 0 ? ($this->grossProfit / $this->totalRevenue) * 100 : 0;
        
        // Warehouse Space Utilization
        $activeStock = BinProductStock::sum('quantity');
        $totalCapacity = 100000; 
        $this->warehouseUtilization = $totalCapacity > 0 ? min(100, round(($activeStock / $totalCapacity) * 100, 1)) : 0;

        // Active Fleet status
        $this->totalVehiclesCount = \DB::table('vehicles')->count();
        $this->activeVehiclesCount = Shipment::where('status', 'in_transit')->distinct('vehicle_id')->count();
        if ($this->activeVehiclesCount === 0) {
            $this->activeVehiclesCount = SalesOrder::where('status', 'shipped')->count();
        }
        if ($this->totalVehiclesCount > 0) {
            $this->activeVehiclesCount = min($this->totalVehiclesCount, $this->activeVehiclesCount);
            $this->fleetUtilization = round(($this->activeVehiclesCount / $this->totalVehiclesCount) * 100);
        } else {
            $this->activeVehiclesCount = 0;
            $this->fleetUtilization = 0;
        }

        // AI Insights
        $this->aiInsights = [];
        if ($this->accountsPayableUnpaid > $this->accountsReceivableUnpaid) {
            $this->aiInsights[] = "⚠️ Accounts Payable is higher than Receivables. Cash flow might be tight this period.";
        }
        if ($this->lowStockCount > 0) {
            $this->aiInsights[] = "🚀 High velocity detected causing {$this->lowStockCount} products to hit critical levels. Generate POs via Intelligence Hub.";
        }
        if ($this->netMargin > 20) {
            $this->aiInsights[] = "📈 Excellent profitability detected! Net margin is holding strong at " . number_format($this->netMargin, 1) . "%.";
        }
        if ($this->warehouseUtilization > 80) {
            $this->aiInsights[] = "⚠️ Warehouse storage is approaching maximum capacity ({$this->warehouseUtilization}%). Suggest stock allocation adjustments.";
        }
        if (empty($this->aiInsights)) {
            $this->aiInsights[] = "✅ Operations are running smoothly. No critical anomalies detected.";
        }
    }

    public function formatCurrency($amount) {
        if ($amount >= 1000000) return number_format($amount / 1000000, 2) . 'M';
        if ($amount >= 100000) return number_format($amount / 1000, 0) . 'k';
        if ($amount >= 1000) return number_format($amount / 1000, 1) . 'k';
        return number_format($amount, 2);
    }

    public function setTimeframe($value)
    {
        $this->timeframe = $value;
        $this->loadData();
        $this->dispatch('timeframe-changed', [
            'chartDates' => $this->chartDates,
            'chartRevenue' => $this->chartRevenue,
            'chartSpend' => $this->chartSpend,
            'topProductsLabels' => $this->topProductsLabels,
            'topProductsSeries' => $this->topProductsSeries,
            'topProductsList' => $this->topProductsList,
        ]);
    }
};

?>

<div>

    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
            {{ __('SCM Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <!-- 1. Identity Spotlight Card -->
            <div class="bg-gradient-to-r from-gray-900 via-indigo-950 to-slate-900 rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden border border-indigo-500/20">
                <!-- Background ambient glow -->
                <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-violet-600/10 rounded-full blur-3xl"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <!-- User Gradient Avatar -->
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center text-white text-2xl font-bold shadow-lg shadow-indigo-500/30">
                            {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                        </div>
                        <div>
                            <span class="text-indigo-400 text-xs font-semibold uppercase tracking-wider">Operational Identity Verified</span>
                            <h3 class="text-white text-2xl font-extrabold tracking-tight mt-0.5">
                                @php
                                    $hour = date('H');
                                    $greeting = 'Good evening';
                                    if ($hour < 12) $greeting = 'Good morning';
                                    elseif ($hour < 17) $greeting = 'Good afternoon';
                                @endphp
                                {{ $greeting }}, {{ auth()->user()->name }}!
                            </h3>
                            <p class="text-indigo-200/80 text-sm mt-1 flex items-center gap-2">
                                <span class="bg-indigo-500/20 px-2.5 py-0.5 rounded-full text-xs font-semibold text-indigo-300 border border-indigo-500/30">
                                    {{ auth()->user()->can('manage users') ? 'System Administrator' : 'Operations Manager' }}
                                </span>
                                <span class="text-indigo-400/60">•</span>
                                <span class="text-gray-300 text-xs">{{ auth()->user()->email }}</span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Live Server Metrics -->
                    <div class="flex flex-wrap items-center gap-4 bg-white/5 backdrop-blur-md rounded-2xl p-4 border border-white/10 w-full md:w-auto">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-semibold text-emerald-400">All Systems Functional</span>
                        </div>
                        <div class="text-xs text-slate-400 border-l border-white/10 pl-4">
                            Server Time: <span class="text-white font-mono font-semibold">{{ now()->format('H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Operations Timeframe Filter (Control Panel) -->
            <div class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 flex flex-col lg:flex-row items-center justify-between gap-4 transition-all duration-200">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                        <!-- Calendar icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Dynamic Reporting Interval</h4>
                        <p class="text-xs text-slate-400">Select reporting period to dynamically filter and reload key indicators, audit logs, and charts.</p>
                    </div>
                </div>

                <!-- Interval Buttons -->
                <div class="flex flex-wrap items-center bg-slate-50 border border-slate-100 rounded-2xl p-1 gap-1 w-full lg:w-auto">
                    @foreach([
                        'today' => 'Today', 
                        '7_days' => '7 Days', 
                        '30_days' => '30 Days', 
                        'this_month' => 'This Month', 
                        'all_time' => 'All Time'
                    ] as $key => $label)
                        <button 
                            wire:click="setTimeframe('{{ $key }}')"
                            class="px-4 py-2 text-xs font-bold rounded-xl transition-all duration-200 flex-1 lg:flex-initial text-center {{ $timeframe === $key ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-150/80' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            
            <!-- ADVANCED: AI Insights Banner -->
            @if(count($aiInsights) > 0)
            <div class="bg-gradient-to-r from-indigo-50 to-violet-50 rounded-2xl p-4 shadow-sm border border-indigo-100 flex items-start gap-4">
                <div class="p-2 bg-white rounded-xl shadow-sm text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-indigo-900">AI Business Insights</h4>
                    <ul class="mt-1 space-y-1">
                        @foreach($aiInsights as $insight)
                            <li class="text-xs font-medium text-indigo-700/80">{{ $insight }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            <!-- ADVANCED: Quick Action Hub -->
            <div class="flex flex-wrap gap-3">
                <a href="{{ url('/procurement/purchase-orders') }}" class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 shadow-sm transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg> New PO
                </a>
                <a href="{{ url('/sales/orders') }}" class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 shadow-sm transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg> New Sales Order
                </a>
                <a href="{{ url('/finance/expenses') }}" class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 shadow-sm transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg> Log Expense
                </a>
            </div>

            <!-- 2. Rich KPIs Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5 gap-6">
                <!-- Revenue Card -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden group hover:shadow-md transition-all duration-200">
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Revenue</span>
                        <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
                            <!-- Dollar Icon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl xl:text-3xl font-black text-gray-900 tracking-tight truncate" title="{{ setting('currency_symbol', '$') }}{{ number_format($totalRevenue, 2) }}">{{ setting('currency_symbol', '$') }}{{ $this->formatCurrency($totalRevenue) }}</h3>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <span class="text-indigo-600 font-semibold">Sales Orders</span>
                            in timeframe
                        </p>
                    </div>
                </div>

                <!-- Procurement Spend Card -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden group hover:shadow-md transition-all duration-200">
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Procurement Spend</span>
                        <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                            <!-- Spend Icon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl xl:text-3xl font-black text-gray-900 tracking-tight truncate" title="{{ setting('currency_symbol', '$') }}{{ number_format($totalSpend, 2) }}">{{ setting('currency_symbol', '$') }}{{ $this->formatCurrency($totalSpend) }}</h3>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <span class="text-emerald-600 font-semibold">POs + Expenses</span>
                            in timeframe
                        </p>
                    </div>
                </div>

                <!-- Low Stock Warning Card -->
                <a href="#low-stock-panel" class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden group hover:shadow-md transition-all duration-200 block">
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Low Stock Alerts</span>
                        <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl group-hover:animate-bounce">
                            <!-- Alert Icon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-3xl font-black text-gray-900 tracking-tight flex items-baseline gap-2">
                            {{ $lowStockCount }}
                            @if($lowStockCount > 0)
                                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-red-100 text-red-800 animate-pulse">Critical</span>
                            @endif
                        </h3>
                        <p class="text-xs text-gray-400 mt-1">Products below safety limits</p>
                    </div>
                </a>

                
                <!-- ADVANCED: Profitability Card -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden group hover:shadow-md transition-all duration-200">
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Gross Profit</span>
                        <div class="p-2.5 bg-violet-50 text-violet-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl xl:text-3xl font-black text-gray-900 tracking-tight truncate" title="{{ setting('currency_symbol', '$') }}{{ number_format($grossProfit, 2) }}">{{ setting('currency_symbol', '$') }}{{ $this->formatCurrency($grossProfit) }}</h3>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <span class="text-violet-600 font-bold">{{ number_format($netMargin, 1) }}%</span>
                            Net Margin
                        </p>
                    </div>
                </div>

                <!-- Deliveries Card -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-150 relative overflow-hidden group hover:shadow-md transition-all duration-200">
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-sky-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">In Transit Shipments</span>
                        <div class="p-2.5 bg-sky-50 text-sky-600 rounded-xl">
                            <!-- Delivery Icon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 10-4 0 2 2 0 004 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-3xl font-black text-gray-900 tracking-tight">{{ $inTransitShipments }}</h3>
                        <p class="text-xs text-gray-400 mt-1">Active fleet deliveries</p>
                    </div>
                </div>
            </div>

            <!-- ADVANCED: ApexCharts Visualizations & Operational Capacity -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" wire:ignore>
                <!-- Revenue vs Spend Chart -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 lg:col-span-2">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Revenue vs Procurement Spend</h3>
                    <div id="revenueSpendChart" class="w-full h-72"></div>
                </div>
                
                <!-- Supply Chain Operations Health Cards -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 flex flex-col justify-between" wire:ignore.self>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Operational Capacity</h3>
                        <p class="text-xs text-gray-400 mb-6">Real-time logistics and warehouse resource allocation</p>
                        
                        <div class="space-y-6">
                            <!-- Warehouse Utilization -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-600 flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                        Warehouse Storage Utilization
                                    </span>
                                    <span class="text-sm font-extrabold text-gray-900">{{ $warehouseUtilization }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3">
                                    <div class="bg-indigo-600 h-3 rounded-full transition-all duration-500" style="width: {{ $warehouseUtilization }}%"></div>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-400">
                                    <span>Active stock in warehouse bins</span>
                                    <span>Total Capacity: 100k Units</span>
                                </div>
                            </div>

                            <!-- Fleet Utilization -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-600 flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                        Logistics Fleet Deployment
                                    </span>
                                    <span class="text-sm font-extrabold text-gray-900">{{ $fleetUtilization }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3">
                                    <div class="bg-emerald-500 h-3 rounded-full transition-all duration-500" style="width: {{ $fleetUtilization }}%"></div>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-400">
                                    <span>{{ $activeVehiclesCount }} of {{ $totalVehiclesCount }} vehicles active in transit</span>
                                    <span>All drivers licensed & verified</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-100 pt-4 mt-6">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <a href="{{ url('/warehouses') }}" class="text-indigo-600 hover:text-indigo-800">Storage Bins</a>
                            <a href="{{ url('/logistics/vehicles') }}" class="text-indigo-600 hover:text-indigo-800">Fleet Control</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rebuilt Section: Top Product Sales & Velocity Tracker (Split Detailed Grid + Chart) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Detailed Data Table -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 lg:col-span-2">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Top Velocity Products & Turnover</h3>
                        <p class="text-xs text-gray-400">Fastest-moving items ranked by total units sold and revenue contribution</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                    <th class="px-4 py-2.5 rounded-l-xl">Product / SKU</th>
                                    <th class="px-4 py-2.5">Category</th>
                                    <th class="px-4 py-2.5 text-center">Units Sold</th>
                                    <th class="px-4 py-2.5 text-right">Revenue Generated</th>
                                    <th class="px-4 py-2.5 rounded-r-xl text-center">Sales Velocity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse($topProductsList as $tp)
                                    <tr class="hover:bg-gray-50/50 transition-colors text-sm">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-bold text-gray-900">{{ $tp['name'] }}</div>
                                            <div class="text-xs text-gray-400 font-mono">{{ $tp['sku'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500">
                                            {{ $tp['category'] ?: 'Uncategorized' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center font-extrabold text-gray-900">
                                            {{ $tp['total_sold'] }} units
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right font-extrabold text-indigo-600">
                                            {{ setting('currency_symbol', '$') }}{{ number_format($tp['total_revenue'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $tp['badge_color'] }}">
                                                {{ $tp['velocity'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                                            No sales data available for this timeframe.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Right: Chart Share representation -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150" wire:ignore>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Volume Distribution</h3>
                    <div id="topProductsChart" class="w-full h-72 flex items-center justify-center"></div>
                </div>
            </div>

            <!-- 4. Accounts Finance outstanding & Stock Alerts row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" id="low-stock-panel">
                
                <!-- Stock Alerts Table -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 lg:col-span-2 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    Critical Replenishment Alerts
                                    @if($lowStockCount > 0)
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                                    @endif
                                </h3>
                                <p class="text-xs text-gray-400">Products requiring urgent purchase orders / stock corrections</p>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-lg">
                                Total: {{ $lowStockCount }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                        <th class="px-4 py-2.5 rounded-l-xl">SKU / Product</th>
                                        <th class="px-4 py-2.5">Category</th>
                                        <th class="px-4 py-2.5 text-center">Reorder Limit</th>
                                        <th class="px-4 py-2.5 text-center">Current Quantity</th>
                                        <th class="px-4 py-2.5 rounded-r-xl text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse($lowStockProducts as $lowProduct)
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="font-semibold text-gray-900 text-sm">{{ $lowProduct['name'] }}</div>
                                                <div class="text-xs text-gray-400 font-mono">{{ $lowProduct['sku'] }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {{ $lowProduct['category'] ?: 'Uncategorized' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center font-semibold">
                                                {{ $lowProduct['reorder_level'] }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <span class="text-sm font-black text-red-600 bg-red-50 px-2.5 py-0.5 rounded-full border border-red-200">
                                                    {{ $lowProduct['current_stock'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                                <a href="{{ url('/procurement/purchase-orders') }}" class="inline-flex items-center justify-center px-3 py-1 text-xs font-semibold bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-lg transition-all duration-200">
                                                    + PO
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                                🎉 All items are fully stocked above safety limits.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if(count($lowStockProducts) > 0)
                        <div class="border-t border-gray-100 pt-4 text-center mt-4">
                            <a href="{{ url('/warehouses/stock-take') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                Schedule Warehouse Stock Take &rarr;
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Finance Status Highlights -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150 flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Accounts Outstanding</h3>
                        <p class="text-xs text-gray-400 mb-6">Financial snapshot of unpaid invoices vs expenses within timeframe</p>

                        <div class="space-y-6">
                            <!-- Accounts Receivable -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-600 flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                        Accounts Receivable (A/R)
                                    </span>
                                    <span class="text-sm font-extrabold text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($accountsReceivableUnpaid, 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3">
                                    @php
                                        $totalOutstanding = $accountsReceivableUnpaid + $accountsPayableUnpaid;
                                        $arPct = $totalOutstanding > 0 ? ($accountsReceivableUnpaid / $totalOutstanding) * 100 : 0;
                                    @endphp
                                    <div class="bg-indigo-600 h-3 rounded-full transition-all duration-500" style="width: {{ $arPct }}%"></div>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-400">
                                    <span>Unpaid Customer Invoices</span>
                                    <span>{{ number_format($arPct, 0) }}% share</span>
                                </div>
                            </div>

                            <!-- Accounts Payable -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-600 flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                                        Accounts Payable (A/P)
                                    </span>
                                    <span class="text-sm font-extrabold text-gray-900">{{ setting('currency_symbol', '$') }}{{ number_format($accountsPayableUnpaid, 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-3">
                                    @php
                                        $apPct = $totalOutstanding > 0 ? ($accountsPayableUnpaid / $totalOutstanding) * 100 : 0;
                                    @endphp
                                    <div class="bg-red-500 h-3 rounded-full transition-all duration-500" style="width: {{ $apPct }}%"></div>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-400">
                                    <span>Unpaid Supplier Bills</span>
                                    <span>{{ number_format($apPct, 0) }}% share</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4 mt-6">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <a href="{{ url('/finance/receivables') }}" class="text-indigo-600 hover:text-indigo-800">A/R List</a>
                            <a href="{{ url('/finance/payables') }}" class="text-indigo-600 hover:text-indigo-800">A/P List</a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 5. Recent Operations Timeline Logs -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-150">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Recent Inventory Movements & Logistics</h3>
                        <p class="text-xs text-gray-400">Real-time recording log of manual adjustments and automated transactions</p>
                    </div>
                    <a href="{{ url('/inventory/log') }}" class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                        Full Inventory Ledger &rarr;
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-150">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50/50">
                                <th class="px-6 py-3.5 rounded-l-xl">Movement Stamp</th>
                                <th class="px-6 py-3.5">Product Details</th>
                                <th class="px-6 py-3.5">Transaction Type</th>
                                <th class="px-6 py-3.5 text-center">Quantity</th>
                                <th class="px-6 py-3.5 rounded-r-xl">Responsible Staff</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($recentTransactions as $tx)
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">
                                        {{ $tx->created_at->diffForHumans() }}
                                        <div class="text-[10px] text-gray-400 mt-0.5">{{ $tx->created_at->format(setting('date_format', 'Y-m-d') . ' ' . setting('time_format', 'H:i')) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ $tx->product->name ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-400 font-mono">SKU: {{ $tx->product->sku ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @php
                                            $badgeClass = 'bg-gray-100 text-gray-800';
                                            $typeName = $tx->type;
                                            if ($tx->type === 'IN' || $tx->type === 'adjustment_in') {
                                                $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                                $typeName = 'Stock In';
                                            } elseif ($tx->type === 'OUT' || $tx->type === 'adjustment_out') {
                                                $badgeClass = 'bg-rose-50 text-rose-700 border border-rose-200';
                                                $typeName = 'Stock Out';
                                            }
                                        @endphp
                                        <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-lg {{ $badgeClass }}">
                                            {{ $typeName }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-center font-extrabold text-gray-900">
                                        {{ $tx->quantity }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-700">
                                                {{ substr($tx->user->name ?? 'S', 0, 1) }}
                                            </div>
                                            <span>{{ $tx->user->name ?? 'System Process' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">
                                        No recent warehouse inventory movements found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            // Revenue vs Spend Chart
            var revOptions = {
                series: [{
                    name: 'Revenue',
                    data: @json($chartRevenue)
                }, {
                    name: 'Spend',
                    data: @json($chartSpend)
                }],
                chart: {
                    height: 280,
                    type: 'area',
                    fontFamily: 'Inter, sans-serif',
                    toolbar: { show: false }
                },
                colors: ['#4f46e5', '#10b981'],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: {
                    categories: @json($chartDates),
                    labels: { style: { colors: '#9ca3af' } }
                },
                yaxis: {
                    labels: { style: { colors: '#9ca3af' } }
                },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
                }
            };
            var revChart = new ApexCharts(document.querySelector("#revenueSpendChart"), revOptions);
            revChart.render();

            // Top Products Donut
            var topOptions = {
                series: @json($topProductsSeries),
                labels: @json($topProductsLabels),
                chart: { type: 'donut', height: 280, fontFamily: 'Inter, sans-serif' },
                colors: ['#6366f1', '#8b5cf6', '#d946ef', '#f43f5e'],
                dataLabels: { enabled: false },
                legend: { position: 'bottom' }
            };
            var topChart = new ApexCharts(document.querySelector("#topProductsChart"), topOptions);
            topChart.render();
            
            // Re-render charts on timeframe change
            Livewire.on('timeframe-changed', (data) => {
                let eventData = data[0];
                revChart.updateSeries([
                    { name: 'Revenue', data: eventData.chartRevenue },
                    { name: 'Spend', data: eventData.chartSpend }
                ]);
                revChart.updateOptions({
                    xaxis: { categories: eventData.chartDates }
                });
                
                topChart.updateSeries(eventData.topProductsSeries);
                topChart.updateOptions({
                    labels: eventData.topProductsLabels
                });
            });
        });
    </script>
</div>
</div>

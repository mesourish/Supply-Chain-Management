<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\BinProductStock;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\AccountReceivable;
use App\Models\CrmLead;
use App\Models\DataImport;
use Carbon\Carbon;

new class extends Component {
    public $notifications = [];
    public $unreadCount = 0;
    public $activeTab = 'all';

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $alerts = [];
        $readIds = session()->get('read_notifications', []);

        // 1. Low Stock Alerts
        $products = Product::all();
        $stockSums = BinProductStock::groupBy('product_id')
            ->select('product_id', \DB::raw('SUM(quantity) as total_qty'))
            ->pluck('total_qty', 'product_id')
            ->toArray();

        foreach ($products as $product) {
            $qty = $stockSums[$product->id] ?? 0;
            if ($qty <= $product->reorder_level) {
                $id = 'stock_' . $product->id;
                if (!in_array($id, $readIds)) {
                    $alerts[] = [
                        'id' => $id,
                        'category' => 'Stock',
                        'type' => 'warning',
                        'title' => 'Critical Stock Deficit',
                        'message' => "{$product->name} (SKU: {$product->sku}) is below safety limits. Only {$qty} units remaining.",
                        'link' => url('/products'),
                        'time' => 'Action Needed',
                        'created_at' => now(),
                    ];
                }
            }
        }

        // 2. Pending Sales Orders
        $pendingSOs = SalesOrder::where('status', 'pending')->with('customer')->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($pendingSOs as $so) {
            $id = 'so_' . $so->id;
            if (!in_array($id, $readIds)) {
                $customerName = $so->customer->name ?? 'Unknown Customer';
                $alerts[] = [
                    'id' => $id,
                    'category' => 'Sales',
                    'type' => 'info',
                    'title' => 'Pending Sales Order',
                    'message' => "SO #{$so->id} from {$customerName} of $" . number_format($so->total_amount, 2) . " requires review.",
                    'link' => url('/sales/orders'),
                    'time' => $so->created_at->diffForHumans(),
                    'created_at' => $so->created_at,
                ];
            }
        }

        // 3. Draft Purchase Orders
        $draftPOs = PurchaseOrder::where('status', 'draft')->with('supplier')->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($draftPOs as $po) {
            $id = 'po_' . $po->id;
            if (!in_array($id, $readIds)) {
                $supplierName = $po->supplier->name ?? 'Unknown Supplier';
                $alerts[] = [
                    'id' => $id,
                    'category' => 'Procurement',
                    'type' => 'success',
                    'title' => 'Draft PO Awaiting Approval',
                    'message' => "PO #{$po->id} for {$supplierName} of $" . number_format($po->total_amount, 2) . " needs authorization.",
                    'link' => url('/procurement/purchase-orders'),
                    'time' => $po->created_at->diffForHumans(),
                    'created_at' => $po->created_at,
                ];
            }
        }

        // 4. Overdue/Unpaid Accounts Receivable
        $unpaidARs = AccountReceivable::where('status', 'unpaid')->with('customer')->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($unpaidARs as $ar) {
            $id = 'ar_' . $ar->id;
            if (!in_array($id, $readIds)) {
                $customerName = $ar->customer->name ?? 'Unknown Customer';
                $alerts[] = [
                    'id' => $id,
                    'category' => 'Finance',
                    'type' => 'danger',
                    'title' => 'Unpaid Invoice Outstanding',
                    'message' => "AR balance of $" . number_format($ar->amount, 2) . " from {$customerName} is unpaid.",
                    'link' => url('/finance/receivables'),
                    'time' => $ar->created_at->diffForHumans(),
                    'created_at' => $ar->created_at,
                ];
            }
        }

        // 5. New CRM Leads
        $newLeads = CrmLead::whereIn('pipeline_stage', ['new', 'contacted'])->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($newLeads as $lead) {
            $id = 'lead_' . $lead->id;
            if (!in_array($id, $readIds)) {
                $company = $lead->company_name ?: 'Private Individual';
                $alerts[] = [
                    'id' => $id,
                    'category' => 'CRM',
                    'type' => 'indigo',
                    'title' => 'New Opportunity',
                    'message' => "{$lead->contact_name} from {$company} registered as a hot lead (Est: $" . number_format($lead->deal_value, 2) . ").",
                    'link' => url('/crm/leads'),
                    'time' => $lead->created_at->diffForHumans(),
                    'created_at' => $lead->created_at,
                ];
            }
        }


        // 6. Active Data Imports
        $activeImports = DataImport::whereIn('status', ['pending', 'processing'])->get();
        foreach ($activeImports as $import) {
            $id = 'import_' . $import->id;
            
            $total = $import->total_rows > 0 ? $import->total_rows : 1;
            $current = $import->processed_rows + $import->failed_rows;
            $percentage = min(100, round(($current / $total) * 100));

            $alerts[] = [
                'id' => $id,
                'category' => 'System',
                'type' => 'indigo',
                'title' => ucfirst($import->type) . ' Import in Progress',
                'message' => "Uploading data... {$percentage}% complete ({$current}/{$import->total_rows} rows).",
                'progress' => $percentage,
                'link' => url('/admin/imports'),
                'time' => 'In Progress',
                'created_at' => $import->created_at,
            ];
        }

        // Sort all alerts by date/time (Stock warnings float to top as urgent)
        usort($alerts, function ($a, $b) {
            if ($a['category'] === 'Stock' && $b['category'] !== 'Stock') return -1;
            if ($b['category'] === 'Stock' && $a['category'] !== 'Stock') return 1;
            return strcmp($b['created_at'], $a['created_at']);
        });

        $this->notifications = $alerts;
        $this->unreadCount = count($alerts);
    }

    public function markAsRead($id)
    {
        $read = session()->get('read_notifications', []);
        $read[] = $id;
        session()->put('read_notifications', $read);
        $this->loadNotifications();
    }

    public function clearAll()
    {
        $read = session()->get('read_notifications', []);
        foreach ($this->notifications as $notif) {
            $read[] = $notif['id'];
        }
        session()->put('read_notifications', array_unique($read));
        $this->loadNotifications();
    }
};

?>

<div x-data="{ open: false }" class="relative z-50" wire:poll.10s="loadNotifications">
    <!-- Notification Bell Trigger -->
    <button @click="open = !open" 
            class="w-9 h-9 flex items-center justify-center rounded-xl transition-all duration-200 relative bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 hover:text-indigo-600 focus:outline-none"
            :class="{ 'bg-indigo-50 border-indigo-200 text-indigo-600': open }">
        <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        
        <!-- Pulsing Unread Badge Count -->
        @if($unreadCount > 0)
            <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-[10px] font-extrabold text-white items-center justify-center shadow-sm">
                    {{ $unreadCount }}
                </span>
            </span>
        @endif
    </button>

    <!-- Dropdown Panel (Glassmorphic & Styled) -->
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         @click.outside="open = false" 
         class="absolute right-0 mt-3.5 w-[380px] sm:w-[420px] bg-white/95 backdrop-blur-md rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden"
         style="display: none;">
        
        <!-- Header -->
        <div class="p-4 bg-gradient-to-r from-slate-900 to-indigo-950 text-white flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span class="p-1 bg-white/10 rounded-lg text-indigo-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </span>
                <h3 class="font-extrabold text-sm tracking-tight">Enterprise Operations Alerts</h3>
            </div>
            @if($unreadCount > 0)
                <button wire:click="clearAll" class="text-[11px] font-bold text-indigo-300 hover:text-white transition-colors bg-white/10 hover:bg-white/20 px-2.5 py-1 rounded-lg">
                    Clear All
                </button>
            @endif
        </div>

        <!-- Filter Tabs -->
        <div class="flex border-b border-slate-100 bg-slate-50/50 p-1.5 gap-1 select-none">
            @foreach([
                'all' => 'All',
                'stock' => '⚠️ Stock',
                'sales' => '💰 Sales/AR',
                'crm' => '🎯 CRM',
                'proc' => '📦 Procure'
            ] as $tabKey => $tabLabel)
                <button @click="$wire.set('activeTab', '{{ $tabKey }}')"
                        class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition-all duration-200 flex-1 text-center {{ $activeTab === $tabKey ? 'bg-white shadow-sm border border-slate-200 text-indigo-600' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        <!-- Alerts List Content -->
        <div class="max-h-[350px] overflow-y-auto divide-y divide-slate-100">
            @php
                $filtered = array_filter($notifications, function($notif) use ($activeTab) {
                    if ($activeTab === 'all') return true;
                    if ($activeTab === 'stock') return $notif['category'] === 'Stock';
                    if ($activeTab === 'sales') return in_array($notif['category'], ['Sales', 'Finance']);
                    if ($activeTab === 'crm') return $notif['category'] === 'CRM';
                    if ($activeTab === 'proc') return in_array($notif['category'], ['Procurement']);
                    return true;
                });
            @endphp

            @forelse($filtered as $notif)
                <div class="p-3.5 hover:bg-slate-50/70 transition-colors flex items-start justify-between gap-3 group relative">
                    <!-- Category Specific Icon Circle -->
                    <div class="flex-shrink-0 mt-0.5">
                        @php
                            $circleColor = 'bg-slate-100 text-slate-600';
                            if ($notif['type'] === 'warning') $circleColor = 'bg-amber-50 text-amber-600 border border-amber-200';
                            elseif ($notif['type'] === 'info') $circleColor = 'bg-indigo-50 text-indigo-600 border border-indigo-200';
                            elseif ($notif['type'] === 'success') $circleColor = 'bg-emerald-50 text-emerald-600 border border-emerald-200';
                            elseif ($notif['type'] === 'danger') $circleColor = 'bg-rose-50 text-rose-600 border border-rose-200';
                            elseif ($notif['type'] === 'purple') $circleColor = 'bg-purple-50 text-purple-600 border border-purple-200';
                            elseif ($notif['type'] === 'indigo') $circleColor = 'bg-blue-50 text-blue-600 border border-blue-200';
                        @endphp
                        
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center {{ $circleColor }} shadow-sm">
                            @if($notif['category'] === 'Stock')
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            @elseif($notif['category'] === 'Sales')
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            @elseif($notif['category'] === 'Procurement')
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            @elseif($notif['category'] === 'Finance')
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @elseif($notif['category'] === 'CRM')
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            @elseif($notif['category'] === 'System')
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $notif['category'] }}</span>
                            <span class="text-[10px] font-medium text-slate-400 font-mono">{{ $notif['time'] }}</span>
                        </div>
                        <a href="{{ $notif['link'] }}" class="block font-bold text-slate-800 text-xs mt-0.5 hover:text-indigo-600 transition-colors cursor-pointer leading-snug">
                            {{ $notif['title'] }}
                        </a>
                        <p class="text-slate-500 text-[11px] mt-0.5 leading-relaxed truncate-2-lines">
                            {{ $notif['message'] }}
                        </p>
                        
                        @if(isset($notif['progress']))
                            <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ $notif['progress'] }}%"></div>
                            </div>
                        @endif
                    </div>

                    <!-- Dismiss/Mark as read Button -->
                    <div class="flex-shrink-0 self-center">
                        <button wire:click="markAsRead('{{ $notif['id'] }}')" 
                                class="p-1.5 rounded-lg text-slate-300 hover:text-slate-600 hover:bg-slate-100 transition-all focus:outline-none"
                                title="Mark as read">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <!-- Empty State -->
                <div class="p-10 text-center flex flex-col items-center justify-center">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 border border-emerald-100 flex items-center justify-center mb-3.5 shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                        </svg>
                    </div>
                    <h4 class="text-xs font-bold text-slate-800">All caught up!</h4>
                    <p class="text-[11px] text-slate-400 mt-1">There are no unread notifications matching this filter.</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="p-3 bg-slate-50 border-t border-slate-100 text-center flex-shrink-0">
            <button @click="open = false" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800 transition-colors focus:outline-none">
                Close Panel
            </button>
        </div>
    </div>
</div>

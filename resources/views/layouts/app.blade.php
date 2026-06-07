<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ setting('website_name', config('app.name', 'SCM ERP')) }}</title>
        @if(setting('website_favicon'))
            <link rel="icon" type="image/png" href="{{ setting('website_favicon') }}">
        @endif
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Tailwind CDN -->
        <script src="https://cdn.tailwindcss.com"></script>

        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

        <style>
            /* ── Font & reset ── */
            * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
            [x-cloak] { display: none !important; }

            /* ── Scrollbar styling ── */
            ::-webkit-scrollbar { width: 5px; height: 5px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.3); border-radius: 99px; }
            ::-webkit-scrollbar-thumb:hover { background: rgba(99,102,241,0.6); }

            /* ── Sidebar ── */
            .sidebar {
                background: linear-gradient(180deg, #0c0e1a 0%, #0f1223 60%, #0a0d1c 100%);
                border-right: 1px solid rgba(255,255,255,0.06);
                position: relative;
                overflow: hidden;
            }
            .sidebar::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 200px;
                background: radial-gradient(ellipse at 50% 0%, rgba(99,102,241,0.12) 0%, transparent 70%);
                pointer-events: none;
            }

            /* ── Nav items ── */
            .nav-item {
                display: flex; align-items: center;
                padding: 9px 14px; border-radius: 10px;
                font-size: 13.5px; font-weight: 500;
                color: rgba(255,255,255,0.5);
                transition: all .18s ease;
                position: relative; cursor: pointer;
                gap: 10px;
            }
            .nav-item:hover {
                color: rgba(255,255,255,0.9);
                background: rgba(255,255,255,0.06);
            }
            .nav-item.active {
                color: #fff;
                background: rgba(99,102,241,0.18);
                border: 1px solid rgba(99,102,241,0.25);
            }
            .nav-item.active .nav-icon { color: #818cf8; }
            .nav-item:hover .nav-icon { color: rgba(255,255,255,0.8); }
            .nav-icon { color: rgba(255,255,255,0.3); transition: color .18s; flex-shrink: 0; }

            /* ── Active indicator bar ── */
            .nav-item.active::before {
                content: '';
                position: absolute;
                left: 0; top: 20%; bottom: 20%;
                width: 3px; border-radius: 0 3px 3px 0;
                background: linear-gradient(180deg, #818cf8, #6366f1);
            }

            /* ── Sub-nav ── */
            .sub-nav-item {
                display: block; padding: 7px 12px;
                font-size: 13px; font-weight: 400;
                color: rgba(255,255,255,0.4);
                border-radius: 8px;
                transition: all .15s ease;
            }
            .sub-nav-item:hover {
                color: rgba(255,255,255,0.85);
                background: rgba(255,255,255,0.05);
            }
            .sub-nav-item.active {
                color: #c7d2fe;
                background: rgba(99,102,241,0.12);
            }

            /* ── Section label ── */
            .nav-section-label {
                font-size: 10px; font-weight: 700;
                letter-spacing: .1em; text-transform: uppercase;
                color: rgba(255,255,255,0.2);
                padding: 12px 14px 4px;
            }

            /* ── Top bar ── */
            .topbar {
                background: #ffffff;
                border-bottom: 1px solid #f1f5f9;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }

            /* ── Main content ── */
            .main-content {
                background: #f8fafc;
            }

            /* ── Logo pill ── */
            .logo-pill {
                display: flex; align-items: center; gap: 10px;
                padding: 6px 10px 6px 6px;
                border-radius: 12px;
                transition: background .2s;
            }
            .logo-pill:hover { background: rgba(255,255,255,0.05); }
            .logo-icon {
                width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                display: flex; align-items: center; justify-content: center;
                box-shadow: 0 2px 8px rgba(99,102,241,0.4);
            }

            /* ── User chip ── */
            .user-avatar {
                width: 36px; height: 36px; border-radius: 50%;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                display: flex; align-items: center; justify-content: center;
                color: #fff; font-weight: 700; font-size: 14px;
                flex-shrink: 0;
                box-shadow: 0 2px 8px rgba(99,102,241,0.35);
            }

            /* ── Badge ── */
            .nav-badge {
                font-size: 10px; font-weight: 700;
                padding: 1px 6px; border-radius: 99px;
                background: rgba(99,102,241,0.25);
                color: #a5b4fc;
            }

            /* ── Collapse transition ── */
            [x-collapse] { overflow: hidden; }

            /* ── Breadcrumb ── */
            .breadcrumb { font-size: 13px; color: #64748b; }
            .breadcrumb span { color: #94a3b8; margin: 0 4px; }

            /* ── Page title ── */
            .page-title { font-size: 18px; font-weight: 700; color: #0f172a; line-height: 1.2; }

            /* ── Print Styles ── */
            @media print {
                body, html { height: auto !important; overflow: visible !important; background: white !important; }
                .sidebar, .topbar { display: none !important; }
                [x-data] { height: auto !important; overflow: visible !important; display: block !important; }
                .main-content { overflow: visible !important; height: auto !important; padding: 0 !important; margin: 0 !important; background: transparent !important; }
                .flex-1 { margin-left: 0 !important; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

                /* Modal Print Fix */
                .no-print { display: none !important; }
                .print-content-wrapper { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; z-index: 9999 !important; background: white !important; }
                body > div:not(.fixed) { display: none !important; } /* Hide the main app wrapper when printing modal */
            }
        </style>
    </head>
    <body class="antialiased font-sans" style="background:#f8fafc;">

        <div x-data="{ sidebarOpen: true, isMobile: window.innerWidth < 1024 }"
             x-init="() => { if(window.innerWidth < 1024) sidebarOpen = false; window.addEventListener('resize', () => { isMobile = window.innerWidth < 1024; if(!isMobile) sidebarOpen = true; }) }"
             class="flex h-screen overflow-hidden">

            <!-- ═══════════════════════════════════════
                 SIDEBAR
            ═══════════════════════════════════════ -->
            <aside class="sidebar flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out z-50"
                   :class="sidebarOpen ? 'w-60' : 'w-0 lg:w-[68px] overflow-hidden'"
                   style="position: fixed; inset-y: 0; left: 0; height: 100vh;">

                <!-- Sidebar Header -->
                <div class="flex items-center justify-between px-3 h-16 flex-shrink-0"
                     style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <!-- Logo -->
                    <a href="{{ url('/dashboard') }}" class="logo-pill" x-show="sidebarOpen" style="text-decoration:none;">
                        <div class="logo-icon">
                            @if(setting('website_logo'))
                                <img src="{{ setting('website_logo') }}" class="w-5 h-5 object-contain" alt="">
                            @else
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-white whitespace-nowrap tracking-wide">
                            {{ setting('website_name', 'SCM ERP') }}
                        </span>
                    </a>

                    <!-- Collapsed icon-only logo -->
                    <a href="{{ url('/dashboard') }}" x-show="!sidebarOpen" class="logo-icon mx-auto" style="text-decoration:none; display:none;" :style="!sidebarOpen ? 'display:flex' : ''">
                        @if(setting('website_logo'))
                            <img src="{{ setting('website_logo') }}" class="w-5 h-5 object-contain" alt="">
                        @else
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        @endif
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">

                    <!-- MAIN SECTION -->
                    <div x-show="sidebarOpen" class="nav-section-label">Main</div>

                    <!-- Dashboard -->
                    <a href="{{ url('/dashboard') }}"
                       class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}"
                       title="Dashboard">
                        <svg class="nav-icon w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
                    </a>

                    <!-- CRM -->
                    <a href="{{ url('/crm/leads') }}"
                       class="nav-item {{ request()->is('crm*') ? 'active' : '' }}"
                       title="CRM Leads">
                        <svg class="nav-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" class="whitespace-nowrap">CRM Leads</span>
                    </a>

                    <!-- OPERATIONS SECTION -->
                    <div x-show="sidebarOpen" class="nav-section-label mt-2">Operations</div>

                    <!-- INVENTORY -->
                    @can('view products')
                    <div x-data="{ open: {{ request()->is('products*') || request()->is('warehouses*') || request()->is('inventory*') || request()->is('quality-checks*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('products*') || request()->is('warehouses*') || request()->is('inventory*') || request()->is('quality-checks*') ? 'active' : '' }}"
                                title="Inventory">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Inventory</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            <a href="{{ url('/products') }}"       class="sub-nav-item {{ request()->is('products*') ? 'active' : '' }}">Products</a>
                            <a href="{{ url('/warehouses') }}"     class="sub-nav-item {{ request()->routeIs('warehouses.index','warehouses.show') ? 'active' : '' }}">Warehouses (WMS)</a>
                            <a href="{{ url('/warehouses/stock-take') }}" class="sub-nav-item {{ request()->is('warehouses/stock-take*') ? 'active' : '' }}">Stock Take</a>
                            <a href="{{ url('/inventory/log') }}"  class="sub-nav-item {{ request()->is('inventory/log*') ? 'active' : '' }}">Inventory Log</a>
                            <a href="{{ url('/inventory/adjustments') }}" class="sub-nav-item {{ request()->is('inventory/adjustments*') ? 'active' : '' }}">Stock Adjustments</a>
                            @can('view quality_checks')
                            <a href="{{ url('/quality-checks') }}" class="sub-nav-item {{ request()->is('quality-checks*') ? 'active' : '' }}">Quality Audits</a>
                            @endcan
                        </div>
                    </div>
                    @endcan

                    <!-- PROCUREMENT -->
                    @can('view suppliers')
                    <div x-data="{ open: {{ request()->is('suppliers*') || request()->is('procurement*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('suppliers*') || request()->is('procurement*') ? 'active' : '' }}"
                                title="Procurement">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Procurement</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            <a href="{{ url('/suppliers') }}"                   class="sub-nav-item {{ request()->is('suppliers*') && !request()->is('procurement/rfqs*') ? 'active' : '' }}">Suppliers</a>
                            <a href="{{ url('/procurement/rfqs') }}"            class="sub-nav-item {{ request()->is('procurement/rfqs*') ? 'active' : '' }}">Supplier RFQs</a>
                            <a href="{{ url('/procurement/purchase-orders') }}" class="sub-nav-item {{ request()->is('procurement/purchase-orders*') ? 'active' : '' }}">Purchase Orders</a>
                            <a href="{{ url('/procurement/grn') }}"             class="sub-nav-item {{ request()->is('procurement/grn*') ? 'active' : '' }}">Goods Receipt (GRN)</a>
                        </div>
                    </div>
                    @endcan

                    <!-- SALES -->
                    @can('view customers')
                    <div x-data="{ open: {{ request()->is('customers*') || request()->is('sales*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('customers*') || request()->is('sales*') ? 'active' : '' }}"
                                title="Sales">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Sales</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            <a href="{{ url('/customers') }}"         class="sub-nav-item {{ request()->is('customers*') ? 'active' : '' }}">Customers</a>
                            <a href="{{ url('/sales/quotations') }}"  class="sub-nav-item {{ request()->is('sales/quotations*') ? 'active' : '' }}">Quotations</a>
                            <a href="{{ url('/sales/orders') }}"      class="sub-nav-item {{ request()->is('sales/orders*') ? 'active' : '' }}">Sales Orders</a>
                            @can('view fulfillment')
                            <a href="{{ url('/sales/fulfillment') }}" class="sub-nav-item {{ request()->is('sales/fulfillment*') ? 'active' : '' }}">Order Fulfillment</a>
                            @endcan
                            @can('view returns')
                            <a href="{{ url('/sales/returns') }}"     class="sub-nav-item {{ request()->is('sales/returns*') ? 'active' : '' }}">Returns (RMA)</a>
                            @endcan
                        </div>
                    </div>
                    @endcan

                    <!-- MANUFACTURING -->
                    @can('view manufacturing')
                    <div x-data="{ open: {{ request()->is('manufacturing*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('manufacturing*') ? 'active' : '' }}"
                                title="Manufacturing">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Manufacturing</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            <a href="{{ url('/manufacturing/bom') }}" class="sub-nav-item {{ request()->is('manufacturing/bom*') ? 'active' : '' }}">Bills of Materials</a>
                            <a href="{{ url('/manufacturing/orders') }}" class="sub-nav-item {{ request()->is('manufacturing/orders*') ? 'active' : '' }}">Manufacturing Orders</a>
                        </div>
                    </div>
                    @endcan

                    <!-- FLEET -->
                    @canany(['view vehicles', 'view drivers', 'view shipments'])
                    <div x-data="{ open: {{ request()->is('logistics*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('logistics*') ? 'active' : '' }}"
                                title="Fleet & Logistics">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Fleet & Logistics</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            <a href="{{ url('/logistics/dispatch') }}"  class="sub-nav-item {{ request()->is('logistics/dispatch*') ? 'active' : '' }}">Dispatch Board</a>
                            @can('view vehicles')
                            <a href="{{ url('/logistics/vehicles') }}"  class="sub-nav-item {{ request()->is('logistics/vehicles*') ? 'active' : '' }}">Fleet Command</a>
                            @endcan
                            @can('view drivers')
                            <!-- <a href="{{ url('/logistics/drivers') }}"   class="sub-nav-item {{ request()->is('logistics/drivers*') ? 'active' : '' }}">Driver Intelligence</a> -->
                            @endcan
                            @can('view shipments')
                            <a href="{{ url('/logistics/shipments') }}" class="sub-nav-item {{ request()->is('logistics/shipments*') ? 'active' : '' }}">Shipments</a>
                            @endcan
                        </div>
                    </div>
                    @endcanany

                    <!-- FINANCE SECTION -->
                    <div x-show="sidebarOpen" class="nav-section-label mt-2">Finance</div>

                    <div x-data="{ open: {{ request()->is('finance*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('finance*') ? 'active' : '' }}"
                                title="Finance">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Finance</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            <a href="{{ url('/finance/payables') }}"             class="sub-nav-item {{ request()->is('finance/payables*') ? 'active' : '' }}">Accounts Payable</a>
                            <a href="{{ url('/finance/receivables') }}"          class="sub-nav-item {{ request()->is('finance/receivables*') ? 'active' : '' }}">Accounts Receivable</a>
                            <a href="{{ url('/finance/invoices') }}"             class="sub-nav-item {{ request()->is('finance/invoices*') ? 'active' : '' }}">Invoices</a>
                            <a href="{{ url('/finance/payment-certificates') }}" class="sub-nav-item {{ request()->is('finance/payment-certificates*') ? 'active' : '' }}">Payment Certificates</a>
                            <a href="{{ url('/finance/expenses') }}"             class="sub-nav-item {{ request()->is('finance/expenses*') ? 'active' : '' }}">Purchase Expenses</a>
                            @can('view general_ledger')
                            <a href="{{ url('/finance/ledger') }}"               class="sub-nav-item {{ request()->is('finance/ledger*') ? 'active' : '' }}">General Ledger</a>
                            <a href="{{ url('/finance/accounts') }}"             class="sub-nav-item {{ request()->is('finance/accounts*') ? 'active' : '' }}">Chart of Accounts</a>
                            @endcan
                        </div>
                    </div>

                    <!-- REPORTS SECTION -->
                    <div x-show="sidebarOpen" class="nav-section-label mt-2">Reports & Analytics</div>
                    <div x-data="{ open: {{ request()->is('reports*') || request()->is('inventory/analytics*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('reports*') || request()->is('inventory/analytics*') ? 'active' : '' }}"
                                title="Reports">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Reports</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open" class="pl-12 py-2 space-y-1 bg-slate-900/50 relative sidebar-sub-nav">
                            <div class="absolute left-[38px] top-0 bottom-0 w-px bg-slate-800"></div>
                            <a href="{{ url('/reports') }}" class="sub-nav-item {{ request()->routeIs('reports.index') ? 'active' : '' }}">Comprehensive Reports</a>
                        </div>
                    </div>

                    <!-- ADMIN SECTION -->
                    @canany(['manage users', 'view system_logs'])
                    <div x-show="sidebarOpen" class="nav-section-label mt-2">Administration</div>
                    <div x-data="{ open: {{ request()->is('admin*') ? 'true' : 'false' }} }">
                        <button @click="open = !open; if(!sidebarOpen) { sidebarOpen = true; open = true; }"
                                class="nav-item w-full {{ request()->is('admin*') ? 'active' : '' }}"
                                title="Settings">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap flex-1 text-left">Settings</span>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-3.5 h-3.5 transition-transform duration-200 nav-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-8 pr-1 space-y-0.5 pt-0.5">
                            @can('manage users')
                            <a href="{{ url('/admin/settings') }}" class="sub-nav-item {{ request()->is('admin/settings*') ? 'active' : '' }}">General Settings</a>
                            <a href="{{ url('/admin/constants') }}" class="sub-nav-item {{ request()->is('admin/constants*') ? 'active' : '' }}">System Constants</a>
                            <a href="{{ url('/admin/imports') }}" class="sub-nav-item {{ request()->is('admin/imports*') ? 'active' : '' }}">Bulk Data Imports</a>
                            <a href="{{ url('/admin/users') }}"    class="sub-nav-item {{ request()->is('admin/users*') ? 'active' : '' }}">Users</a>
                            <a href="{{ url('/admin/roles') }}"    class="sub-nav-item {{ request()->is('admin/roles*') ? 'active' : '' }}">Roles & Permissions</a>
                            @endcan
                            @can('view system_logs')
                            <a href="{{ url('/admin/system-logs') }}" class="sub-nav-item {{ request()->is('admin/system-logs*') ? 'active' : '' }}">System Logs</a>
                            @endcan
                        </div>
                    </div>
                    @endcanany

                    {{-- ══ ENTERPRISE AI SECTION ══ --}}
                    <div class="mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
                        <div x-show="sidebarOpen" class="nav-section-label">Enterprise AI</div>

                        {{-- AI Forecasting --}}
                        <a href="{{ route('intelligence.forecasting') }}"
                           title="AI Forecasting"
                           class="nav-item {{ request()->routeIs('intelligence.forecasting') ? 'active' : '' }}">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap">AI Forecasting</span>
                        </a>

                        {{-- Documentation & FAQ --}}
                        <a href="{{ route('faq.index') }}"
                           title="Documentation & FAQ"
                           class="nav-item {{ request()->routeIs('faq.index') ? 'active' : '' }}">
                            <svg class="nav-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-show="sidebarOpen" class="whitespace-nowrap">Docs &amp; FAQ</span>
                        </a>
                    </div>
                </nav>

                <!-- User Section at Bottom -->
                <div class="flex-shrink-0 p-3" style="border-top: 1px solid rgba(255,255,255,0.06);"
                     x-data="{ showUserMenu: false }">
                    <div class="relative">
                        <button @click="showUserMenu = !showUserMenu" @click.outside="showUserMenu = false"
                                class="flex items-center w-full gap-3 p-2 rounded-xl hover:bg-white/5 transition-colors">
                            {{-- Avatar: show profile photo if set, otherwise initials --}}
                            @php $authUser = auth()->user(); @endphp
                            <div class="user-avatar flex-shrink-0 overflow-hidden"
                                 style="{{ $authUser->profile_photo_path ? 'padding:0;' : '' }}">
                                @if($authUser->profile_photo_path && file_exists(storage_path('app/public/' . $authUser->profile_photo_path)))
                                    <img src="{{ asset('storage/' . $authUser->profile_photo_path) }}"
                                         class="w-full h-full object-cover rounded-full" alt="Photo">
                                @else
                                    {{ strtoupper(substr($authUser->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($authUser->name ?? ' ', ' '), 1, 1)) }}
                                @endif
                            </div>
                            <div class="flex-1 min-w-0 text-left" x-show="sidebarOpen">
                                <p class="text-sm font-semibold text-white truncate leading-tight">{{ $authUser->name ?? 'Guest' }}</p>
                                <p class="text-xs truncate mt-0.5" style="color: rgba(255,255,255,0.35);">
                                    {{ $authUser->getRoleNames()->first() ?? 'User' }}
                                </p>
                            </div>
                            <svg x-show="sidebarOpen"
                                 :class="{'rotate-180': showUserMenu}"
                                 class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200"
                                 style="color: rgba(255,255,255,0.3);" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <!-- Rich User Dropdown -->
                        <div x-show="showUserMenu" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute bottom-full left-0 w-64 mb-2 rounded-2xl shadow-2xl z-50 overflow-hidden"
                             style="background: #1a1f35; border: 1px solid rgba(255,255,255,0.1);">

                            {{-- Header with avatar --}}
                            <div class="px-4 py-3" style="background: rgba(99,102,241,0.12); border-bottom: 1px solid rgba(255,255,255,0.07);">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl overflow-hidden flex-shrink-0"
                                         style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
                                        @if($authUser->profile_photo_path && file_exists(storage_path('app/public/' . $authUser->profile_photo_path)))
                                            <img src="{{ asset('storage/' . $authUser->profile_photo_path) }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-white text-sm font-bold">
                                                {{ strtoupper(substr($authUser->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($authUser->name ?? ' ', ' '), 1, 1)) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-white truncate">{{ $authUser->name }}</p>
                                        <p class="text-xs truncate" style="color:rgba(255,255,255,0.45);">{{ $authUser->email }}</p>
                                        @if($authUser->job_title)
                                        <p class="text-xs mt-0.5" style="color:rgba(165,180,252,0.8);">{{ $authUser->job_title }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Menu Items --}}
                            @php
                            $menuItems = [
                                [url('/profile'),           'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',                                                                  'My Profile',           'Overview & profile card',      false],
                                [url('/profile').'?tab=edit', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',           'Edit Information',     'Update personal & work details', false],
                                [url('/profile').'?tab=media','M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'Photo & Signature', 'Upload profile photo & sign', false],
                                [url('/profile').'?tab=security', 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',  'Security',             'Password & account security',  false],
                                [url('/profile').'?tab=activity', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'Activity & Roles',  'Permissions & audit trail',    false],
                            ];
                            @endphp
                            <div class="py-1.5">
                                @foreach($menuItems as [$href, $path, $title, $subtitle, $danger])
                                <a href="{{ $href }}"
                                   class="flex items-center gap-3 px-4 py-2.5 transition-colors group"
                                   style="color: rgba(255,255,255,0.65);"
                                   onmouseover="this.style.background='rgba(255,255,255,0.06)'; this.style.color='#fff'"
                                   onmouseout="this.style.background=''; this.style.color='rgba(255,255,255,0.65)'">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background: rgba(99,102,241,0.15);">
                                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold leading-tight">{{ $title }}</p>
                                        <p class="text-xs leading-tight mt-0.5" style="color:rgba(255,255,255,0.3);">{{ $subtitle }}</p>
                                    </div>
                                </a>
                                @endforeach

                                @can('manage users')
                                <a href="{{ url('/admin/users') }}"
                                   class="flex items-center gap-3 px-4 py-2.5 transition-colors"
                                   style="color: rgba(255,255,255,0.65);"
                                   onmouseover="this.style.background='rgba(255,255,255,0.06)'; this.style.color='#fff'"
                                   onmouseout="this.style.background=''; this.style.color='rgba(255,255,255,0.65)'">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background: rgba(245,158,11,0.15);">
                                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold leading-tight">Manage Users</p>
                                        <p class="text-xs leading-tight mt-0.5" style="color:rgba(255,255,255,0.3);">Admin user management</p>
                                    </div>
                                </a>
                                @endcan
                            </div>

                            {{-- Sign Out --}}
                            <div style="border-top: 1px solid rgba(255,255,255,0.07); padding: 6px 0 4px;">
                                <form method="POST" action="{{ url('/logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="flex items-center gap-3 w-full text-left px-4 py-2.5 transition-colors"
                                            style="color: #f87171;"
                                            onmouseover="this.style.background='rgba(248,113,113,0.08)'"
                                            onmouseout="this.style.background=''">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                             style="background: rgba(248,113,113,0.15);">
                                            <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold leading-tight">Sign Out</p>
                                            <p class="text-xs leading-tight mt-0.5" style="color:rgba(248,113,113,0.5);">End your session</p>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ═══════════════════════════════════════
                 MAIN AREA
            ═══════════════════════════════════════ -->
            <div class="flex-1 flex flex-col h-screen overflow-hidden transition-all duration-300"
                 :style="sidebarOpen ? 'margin-left: 240px' : 'margin-left: 68px'">

                <!-- Top Bar -->
                <header class="topbar flex-shrink-0 flex items-center justify-between px-5 py-0 h-14">
                    <div class="flex items-center gap-3">
                        <!-- Sidebar Toggle -->
                        <button @click="sidebarOpen = !sidebarOpen"
                                class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                style="color: #64748b;"
                                onmouseover="this.style.background='#f1f5f9'; this.style.color='#1e293b'"
                                onmouseout="this.style.background=''; this.style.color='#64748b'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>

                        <!-- Page Title -->
                        @if (isset($header))
                            <div>
                                <h1 class="page-title">{{ $header }}</h1>
                            </div>
                        @endif
                    </div>

                    <!-- Right actions -->
                    <div class="flex items-center gap-2">
                        <!-- Live Dynamic Notifications Bell -->
                        <livewire:layout.notifications-bell />
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto main-content p-5 lg:p-6">
                    {{ $slot }}
                </main>
            </div>

            <!-- Mobile backdrop -->
            <div x-show="sidebarOpen && isMobile"
                 @click="sidebarOpen = false"
                 class="fixed inset-0 bg-black/50 z-40 lg:hidden"
                 x-transition.opacity>
            </div>

        </div>

        <!-- Leaflet JS -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

        <!-- Global Toaster -->
        <div x-data="{ toasts: [] }"
             @toast.window="
                let t = { id: Date.now(), type: $event.detail.type || 'success', message: $event.detail.message };
                toasts.push(t);
                setTimeout(() => { toasts = toasts.filter(toast => toast.id !== t.id) }, 4000);
             "
             class="fixed top-4 right-4 z-50 flex flex-col gap-2">
            <template x-for="toast in toasts" :key="toast.id">
                <div x-show="true" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-x-10"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-x-0"
                     x-transition:leave-end="opacity-0 translate-x-10"
                     class="flex items-center w-full max-w-xs p-4 rounded-lg shadow text-white"
                     :class="{
                         'bg-green-600': toast.type === 'success',
                         'bg-red-600': toast.type === 'error',
                         'bg-blue-600': toast.type === 'info'
                     }"
                     role="alert">
                    <div class="ml-3 text-sm font-normal" x-text="toast.message"></div>
                    <button @click="toasts = toasts.filter(t => t.id !== toast.id)" type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-white hover:text-gray-200 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 inline-flex h-8 w-8" aria-label="Close">
                        <span class="sr-only">Close</span>
                        <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </button>
                </div>
            </template>
        </div>

        @if(session()->has('message'))
            <script>
                document.addEventListener('alpine:init', () => {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: '{{ session('message') }}' }}));
                });
            </script>
        @endif
        @if(session()->has('error'))
            <script>
                document.addEventListener('alpine:init', () => {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: '{{ session('error') }}' }}));
                });
            </script>
        @endif
    </body>
</html>

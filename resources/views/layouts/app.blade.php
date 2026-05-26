<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SCM ERP') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts & Styles via CDN instead of Vite -->
        <script src="https://cdn.tailwindcss.com"></script>

        <!-- Leaflet CSS for Maps -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

        <style>
            body { font-family: 'Inter', sans-serif; }
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="bg-gray-100 text-gray-900 antialiased font-sans">

        <div x-data="{ sidebarOpen: true }" class="flex h-screen overflow-hidden">
            
            <!-- Sidebar -->
            <aside 
                :class="sidebarOpen ? 'translate-x-0 w-64' : '-translate-x-full w-0 lg:w-20 lg:translate-x-0'"
                class="fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-300 ease-in-out bg-gray-900 text-white shadow-xl lg:static lg:h-auto overflow-y-auto"
            >
                <!-- Sidebar Header -->
                <div class="flex items-center justify-between h-16 px-4 bg-gray-800 border-b border-gray-700">
                    <div class="flex items-center gap-2 overflow-hidden whitespace-nowrap" x-show="sidebarOpen || window.innerWidth < 1024">
                        @if(setting('website_logo'))
                            <img src="{{ setting('website_logo') }}" class="w-8 h-8 object-contain" alt="Logo">
                        @else
                            <svg class="w-8 h-8 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        @endif
                        <span class="text-xl font-bold tracking-wider">{{ setting('website_name', 'SCM ERP') }}</span>
                    </div>
                    <!-- Small Logo for Collapsed State on LG screens -->
                    <div class="hidden lg:flex items-center justify-center w-full" x-show="!sidebarOpen" style="display: none;">
                        @if(setting('website_logo'))
                            <img src="{{ setting('website_logo') }}" class="w-8 h-8 object-contain" alt="Logo">
                        @else
                            <svg class="w-8 h-8 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        @endif
                    </div>

                    <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Navigation Links -->
                <nav class="flex-1 px-2 py-4 space-y-2">
                    
                    <!-- Dashboard -->
                    <a href="{{ url('/dashboard') }}" class="flex items-center px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('dashboard') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5 mr-3 {{ request()->is('dashboard') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Dashboard</span>
                    </a>

                    <!-- CRM MODULE -->
                    <a href="{{ url('/crm/leads') }}" class="flex items-center px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('crm*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5 mr-3 {{ request()->is('crm*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">CRM Leads</span>
                    </a>

                    <!-- INVENTORY MODULE -->
                    @can('view products')
                    <div x-data="{ open: {{ request()->is('products*') || request()->is('warehouses*') || request()->is('inventory*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('products*') || request()->is('warehouses*') || request()->is('inventory*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('products*') || request()->is('warehouses*') || request()->is('inventory*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Inventory</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/products') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('products*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Products</a>
                            <a href="{{ url('/warehouses') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->routeIs('warehouses.index', 'warehouses.show') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                                🏭 Warehouses (WMS)
                            </a>

                            <a href="{{ url('/warehouses/stock-take') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('warehouses/stock-take*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                                📋 Stock Take
                            </a>
                            <a href="{{ url('/inventory/log') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('inventory/log*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Inventory Log</a>
                            <a href="{{ url('/inventory/adjustments') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('inventory/adjustments*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Stock Adjustments</a>
                        </div>
                    </div>
                    @endcan

                    <!-- PROCUREMENT MODULE -->
                    @can('view suppliers')
                    <div x-data="{ open: {{ request()->is('suppliers*') || request()->is('procurement*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('suppliers*') || request()->is('procurement*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('suppliers*') || request()->is('procurement*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Procurement</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/suppliers') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('suppliers*') && !request()->is('procurement/rfqs*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Suppliers</a>
                            <a href="{{ url('/procurement/rfqs') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('procurement/rfqs*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Supplier RFQs</a>
                            <a href="{{ url('/procurement/purchase-orders') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('procurement/purchase-orders*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Purchase Orders</a>
                            <a href="{{ url('/procurement/grn') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('procurement/grn*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Goods Receipt (GRN)</a>
                        </div>
                    </div>
                    @endcan

                    <!-- SALES MODULE -->
                    @can('view customers')
                    <div x-data="{ open: {{ request()->is('customers*') || request()->is('sales*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('customers*') || request()->is('sales*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('customers*') || request()->is('sales*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Sales</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/customers') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('customers*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Customers</a>
                            <a href="{{ url('/sales/quotations') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('sales/quotations*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Sales Quotations</a>
                            <a href="{{ url('/sales/orders') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('sales/orders*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Sales Orders</a>
                            @can('view fulfillment')
                            <a href="{{ url('/sales/fulfillment') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('sales/fulfillment*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Order Fulfillment</a>
                            @endcan
                            @can('view returns')
                            <a href="{{ url('/sales/returns') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('sales/returns*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Returns (RMA)</a>
                            @endcan
                        </div>
                    </div>
                    @endcan

                    <!-- FLEET MANAGEMENT MODULE -->
                    @canany(['view vehicles', 'view drivers', 'view shipments'])
                    <div x-data="{ open: {{ request()->is('logistics*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('logistics*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('logistics*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Fleet</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/logistics/dispatch') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('logistics/dispatch*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Dispatch Board</a>
                            @can('view vehicles')
                            <a href="{{ url('/logistics/vehicles') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('logistics/vehicles*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Vehicles</a>
                            @endcan
                            @can('view drivers')
                            <a href="{{ url('/logistics/drivers') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('logistics/drivers*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Drivers</a>
                            @endcan
                            @can('view shipments')
                            <a href="{{ url('/logistics/shipments') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('logistics/shipments*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Shipments</a>
                            @endcan
                        </div>
                    </div>
                    @endcanany

                    <!-- PROJECTS MODULE -->
                    <div x-data="{ open: {{ request()->is('projects*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('projects*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('projects*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Projects</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/projects') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->routeIs('projects.index', 'projects.show') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Project Directory</a>
                        </div>
                    </div>

                    <!-- FINANCE MODULE -->
                    <!-- Changed from the previous permission check to checking for any finance ability or making it visible -->
                    <div x-data="{ open: {{ request()->is('finance*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('finance*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('finance*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Finance</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/finance/payables') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('finance/payables*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Accounts Payable</a>
                            <a href="{{ url('/finance/receivables') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('finance/receivables*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Accounts Receivable</a>
                            <a href="{{ url('/finance/invoices') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('finance/invoices*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Invoices</a>
                            <a href="{{ url('/finance/payment-certificates') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('finance/payment-certificates*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Payment Certificates</a>
                            <a href="{{ url('/finance/expenses') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('finance/expenses*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Purchase Expenses</a>
                        </div>
                    </div>

                    <!-- SYSTEM SETTINGS -->
                    @can('manage users')
                    <div x-data="{ open: {{ request()->is('admin*') ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="open = !open; if(!sidebarOpen) sidebarOpen = true;" class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md group {{ request()->is('admin*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3 {{ request()->is('admin*') ? 'text-indigo-400' : 'text-gray-400 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span x-show="sidebarOpen" class="whitespace-nowrap transition-opacity duration-300">Settings</span>
                            </div>
                            <svg x-show="sidebarOpen" :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open && sidebarOpen" x-collapse class="pl-12 pr-2 space-y-1">
                            <a href="{{ url('/admin/settings') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('admin/settings*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">General Settings</a>
                            <a href="{{ url('/admin/users') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('admin/users*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Users</a>
                            <a href="{{ url('/admin/roles') }}" class="block px-2 py-2 text-sm rounded-md {{ request()->is('admin/roles*') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Roles & Permissions</a>
                        </div>
                    </div>
                    @endcan
                </nav>

                <!-- User Profile / Logout -->
                <div class="p-4 border-t border-gray-800" x-data="{ showUserMenu: false }">
                    <div class="relative">
                        <button @click="showUserMenu = !showUserMenu" @click.outside="showUserMenu = false" class="flex items-center w-full px-2 py-2 text-sm font-medium text-gray-300 rounded-md hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold flex-shrink-0">
                                {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <div class="ml-3 flex-1 min-w-0 text-left" x-show="sidebarOpen">
                                <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name ?? 'Guest' }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                            </div>
                            <svg x-show="sidebarOpen" class="w-4 h-4 ml-1 flex-shrink-0 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        
                        <div x-show="showUserMenu" x-transition class="absolute bottom-full left-0 w-full mb-2 bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 py-1 z-50">
                            <a href="{{ url('/profile') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Profile</a>
                            <form method="POST" action="{{ url('/logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Log Out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col h-screen overflow-hidden">
                <!-- Top Header for Mobile Menu Toggle & Title -->
                <header class="bg-white shadow-sm flex items-center justify-between px-4 py-3 sm:px-6 lg:px-8 border-b border-gray-200">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        @if (isset($header))
                            <h1 class="ml-4 text-xl font-semibold text-gray-900 truncate">
                                {{ $header }}
                            </h1>
                        @endif
                    </div>
                </header>
                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8 relative">
                    {{ $slot }}
                </main>
            </div>
            
            <!-- Mobile Sidebar Backdrop -->
            <div x-show="sidebarOpen" class="fixed inset-0 z-40 bg-gray-900 bg-opacity-50 transition-opacity lg:hidden" @click="sidebarOpen = false" x-transition.opacity></div>

        </div>

        <!-- Leaflet JS for Maps -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    </body>
</html>

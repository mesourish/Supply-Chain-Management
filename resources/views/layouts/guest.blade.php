<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ config('app.name', 'SCM ERP') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50:  '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe',
                            300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1',
                            600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81',
                        },
                        navy: {
                            900: '#0a0f1e', 800: '#0d1530', 700: '#111827',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }

        /* ── Animated orb background ── */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.25;
            animation: orb-float 8s ease-in-out infinite;
        }
        .orb-1 { width: 420px; height: 420px; background: #6366f1; top: -120px; left: -100px; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: #8b5cf6; bottom: -60px; left: 200px; animation-delay: -3s; }
        .orb-3 { width: 200px; height: 200px; background: #3b82f6; top: 40%; right: -40px; animation-delay: -5s; }

        @keyframes orb-float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-30px) scale(1.05); }
        }

        /* ── Mesh SVG overlay ── */
        .mesh-bg {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3Cpattern id='g' width='60' height='60' patternUnits='userSpaceOnUse'%3E%3Cpath d='M0 0h60v60H0z' fill='none'/%3E%3Cpath d='M60 0v60M0 60h60' stroke='%234f46e5' stroke-width='.3' opacity='.25'/%3E%3C/pattern%3E%3C/defs%3E%3Crect fill='url(%23g)' width='100%25' height='100%25'/%3E%3C/svg%3E");
        }

        /* ── Glass card ── */
        .glass-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.12);
        }

        /* ── Module pills ── */
        .module-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px; border-radius: 40px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8); font-size: 13px; font-weight: 500;
            transition: all .2s ease;
        }
        .module-pill:hover { background: rgba(99,102,241,0.2); border-color: #6366f1; color: #fff; }

        /* ── Input styles ── */
        .auth-input {
            width: 100%; padding: 12px 16px; border-radius: 10px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.14);
            color: #fff; font-size: 14px; outline: none;
            transition: border-color .2s, background .2s;
        }
        .auth-input::placeholder { color: rgba(255,255,255,0.35); }
        .auth-input:focus { border-color: #6366f1; background: rgba(99,102,241,0.1); }

        /* ── Label ── */
        .auth-label { display: block; font-size: 12px; font-weight: 600;
            color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }

        /* ── Error text ── */
        .auth-error { font-size: 12px; color: #f87171; margin-top: 4px; }

        /* ── Login button ── */
        .btn-login {
            width: 100%; padding: 13px; border-radius: 10px; font-weight: 700; font-size: 15px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff; border: none; cursor: pointer;
            box-shadow: 0 4px 24px rgba(99,102,241,0.45);
            transition: all .2s ease;
            position: relative; overflow: hidden;
        }
        .btn-login::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, #818cf8, #6366f1);
            opacity: 0; transition: opacity .2s;
        }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 32px rgba(99,102,241,0.55); }
        .btn-login:active { transform: translateY(0); }

        /* ── Supply-chain flow animation ── */
        @keyframes flow-right {
            0%   { transform: translateX(-8px); opacity: 0; }
            20%  { opacity: 1; }
            80%  { opacity: 1; }
            100% { transform: translateX(8px);  opacity: 0; }
        }
        .flow-dot { animation: flow-right 2s ease-in-out infinite; }
        .flow-dot:nth-child(2) { animation-delay: 0.4s; }
        .flow-dot:nth-child(3) { animation-delay: 0.8s; }

        /* ── Fade-in on load ── */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fade-up .6s ease both; }
        .fade-up-1 { animation-delay: .1s; }
        .fade-up-2 { animation-delay: .2s; }
        .fade-up-3 { animation-delay: .35s; }
        .fade-up-4 { animation-delay: .5s; }

        /* checkbox style */
        .auth-checkbox { accent-color: #6366f1; width: 15px; height: 15px; }

        /* scrollbar hide for left panel */
        .no-scroll::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="antialiased" style="background: #0a0f1e; min-height: 100vh;">

<div class="min-h-screen flex">

    {{-- ═══════════════════════════════════════════
         LEFT PANEL — Branding & Features
    ═══════════════════════════════════════════ --}}
    <div class="hidden lg:flex lg:w-[55%] relative overflow-hidden flex-col justify-between p-12 mesh-bg"
         style="background: linear-gradient(135deg, #0a0f1e 0%, #0d1535 50%, #130f2a 100%);">

        <!-- Orbs -->
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>

        <!-- Top Branding -->
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-2">
                @if(setting('website_logo'))
                    <img src="{{ setting('website_logo') }}" class="w-10 h-10 object-contain" alt="Logo">
                @else
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                @endif
                <span class="text-xl font-bold text-white tracking-wide">{{ setting('website_name', 'SCM ERP') }}</span>
            </div>
            <div class="w-10 h-0.5 bg-gradient-to-r from-indigo-500 to-transparent rounded-full ml-1 mt-1"></div>
        </div>

        <!-- Hero Text -->
        <div class="relative z-10 flex-1 flex flex-col justify-center">
            <div class="fade-up fade-up-1">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold tracking-widest uppercase mb-6"
                      style="background: rgba(99,102,241,0.18); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.35);">
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full inline-block" style="box-shadow: 0 0 6px #818cf8;"></span>
                    Enterprise Platform
                </span>
                <h1 class="text-5xl font-extrabold text-white leading-tight mb-4" style="letter-spacing: -0.02em;">
                    Supply Chain<br>
                    <span style="background: linear-gradient(90deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Intelligence</span>
                </h1>
                <p class="text-base font-normal mb-10" style="color: rgba(255,255,255,0.5); max-width: 400px; line-height: 1.7;">
                    End-to-end visibility across procurement, inventory, sales, logistics, finance, and projects — unified in one intelligent platform.
                </p>
            </div>

            <!-- Animated supply-chain flow -->
            <div class="fade-up fade-up-2 mb-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-semibold uppercase tracking-widest" style="color: rgba(255,255,255,0.35);">Live Data Flow</span>
                </div>
                <div class="flex items-center gap-3 p-4 rounded-2xl" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                    <!-- Supplier -->
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.3);">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <span class="text-xs" style="color: rgba(255,255,255,0.4);">Supplier</span>
                    </div>
                    <!-- Flow dots -->
                    <div class="flex gap-1 flex-1 justify-center">
                        <div class="flow-dot w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                        <div class="flow-dot w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                        <div class="flow-dot w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                    </div>
                    <!-- Warehouse -->
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.2); border: 1px solid rgba(139,92,246,0.3);">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <span class="text-xs" style="color: rgba(255,255,255,0.4);">Warehouse</span>
                    </div>
                    <!-- Flow dots -->
                    <div class="flex gap-1 flex-1 justify-center">
                        <div class="flow-dot w-1.5 h-1.5 rounded-full bg-purple-400" style="animation-delay:.2s"></div>
                        <div class="flow-dot w-1.5 h-1.5 rounded-full bg-purple-400" style="animation-delay:.6s"></div>
                        <div class="flow-dot w-1.5 h-1.5 rounded-full bg-purple-400" style="animation-delay:1s"></div>
                    </div>
                    <!-- Customer -->
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(59,130,246,0.2); border: 1px solid rgba(59,130,246,0.3);">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <span class="text-xs" style="color: rgba(255,255,255,0.4);">Customer</span>
                    </div>
                </div>
            </div>

            <!-- Module pills grid -->
            <div class="fade-up fade-up-3">
                <div class="grid grid-cols-2 gap-2 max-w-sm">
                    @php
                        $modules = [
                            ['icon' => '👥', 'label' => 'CRM & Leads'],
                            ['icon' => '🛒', 'label' => 'Procurement'],
                            ['icon' => '📦', 'label' => 'Inventory WMS'],
                            ['icon' => '📊', 'label' => 'Finance & AP/AR'],
                            ['icon' => '🚚', 'label' => 'Fleet & Logistics'],
                            ['icon' => '🏗️', 'label' => 'Projects'],
                        ];
                    @endphp
                    @foreach($modules as $m)
                        <div class="module-pill">
                            <span>{{ $m['icon'] }}</span>
                            <span>{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Bottom legal -->
        <div class="relative z-10 fade-up fade-up-4">
            <p class="text-xs" style="color: rgba(255,255,255,0.25);">
                © {{ date('Y') }} {{ setting('website_name', 'SCM ERP') }} · Enterprise Supply Chain Platform
            </p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════
         RIGHT PANEL — Login Form
    ═══════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-12 relative"
         style="background: linear-gradient(160deg, #0f0c29, #302b63, #24243e);">

        <!-- Background orbs for mobile / right panel -->
        <div class="absolute top-0 right-0 w-72 h-72 rounded-full opacity-20" style="background: radial-gradient(circle, #6366f1, transparent); filter: blur(60px); pointer-events:none;"></div>
        <div class="absolute bottom-0 left-0 w-60 h-60 rounded-full opacity-15" style="background: radial-gradient(circle, #8b5cf6, transparent); filter: blur(60px); pointer-events:none;"></div>

        <!-- Mobile-only logo -->
        <div class="lg:hidden mb-8 text-center">
            <div class="inline-flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <span class="text-xl font-bold text-white">{{ setting('website_name', 'SCM ERP') }}</span>
            </div>
        </div>

        <!-- Card -->
        <div class="w-full max-w-md glass-card rounded-2xl p-8 shadow-2xl fade-up">

            <!-- Header -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-1">Welcome back</h2>
                <p class="text-sm" style="color: rgba(255,255,255,0.45);">Sign in to access your workspace</p>
            </div>

            <!-- Session status -->
            @if(session('status'))
                <div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7;">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}

            <!-- Divider -->
            <div class="mt-8 pt-6" style="border-top: 1px solid rgba(255,255,255,0.08);">
                <p class="text-center text-xs" style="color: rgba(255,255,255,0.25);">
                    Enterprise Supply Chain Intelligence Platform<br>
                    <span style="color: rgba(255,255,255,0.18);">Secured with end-to-end encryption</span>
                </p>
            </div>
        </div>
    </div>
</div>

</body>
</html>

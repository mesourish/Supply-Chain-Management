<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ setting('website_name', config('app.name', 'SCM ERP')) }}</title>
    @if(setting('website_favicon'))
        <link rel="icon" type="image/png" href="{{ setting('website_favicon') }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        
        body {
            background-color: #f9fafb; /* gray-50 */
            color: #111827; /* gray-900 */
        }

        .input-standard {
            width: 100%; 
            padding: 0.625rem 0.875rem; 
            border-radius: 0.375rem;
            border: 1px solid #d1d5db; 
            background-color: #ffffff;
            color: #111827; 
            font-size: 0.875rem; 
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .input-standard:focus {
            outline: none; 
            border-color: #6366f1; 
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .input-standard::placeholder { color: #9ca3af; }
        
        .label-standard { 
            display: block; 
            font-size: 0.875rem; 
            font-weight: 500; 
            color: #374151; 
            margin-bottom: 0.375rem; 
        }

        .btn-primary {
            width: 100%; 
            padding: 0.625rem 1rem; 
            border-radius: 0.375rem;
            background-color: #0f172a; 
            color: #ffffff; 
            font-size: 0.875rem; 
            font-weight: 500;
            transition: all 0.2s; 
            border: 1px solid transparent;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            display: inline-flex; 
            justify-content: center; 
            align-items: center;
        }
        .btn-primary:hover { background-color: #1e293b; }
        .btn-primary:focus { outline: none; box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1); }
        
        .checkbox-custom {
            accent-color: #0f172a;
            width: 1rem; height: 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            cursor: pointer;
        }
    </style>
</head>
<body class="antialiased min-h-screen flex items-center justify-center p-4 sm:p-8">

    <div class="w-full max-w-md">
        
        <!-- Logo / Branding Centered -->
        <div class="flex flex-col items-center justify-center mb-8">
            <div class="w-12 h-12 bg-slate-900 rounded-lg flex items-center justify-center shadow-sm mb-4">
                @if(setting('website_logo'))
                    <img src="{{ setting('website_logo') }}" class="w-8 h-8 object-contain" alt="Logo">
                @else
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                @endif
            </div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ setting('website_name', 'SCM ERP') }}</h1>
            <p class="text-sm text-slate-500 mt-1">Sign in to your account</p>
        </div>

        <!-- The White Login Box -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 sm:p-10">
            
            @if(session('status'))
                <div class="mb-6 p-4 rounded-md bg-emerald-50 border border-emerald-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-emerald-800">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{ $slot }}
            
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-xs text-slate-400 font-medium">
                &copy; {{ date('Y') }} {{ setting('website_name', 'SCM ERP') }}. All rights reserved.
            </p>
        </div>
    </div>
        
</body>
</html>

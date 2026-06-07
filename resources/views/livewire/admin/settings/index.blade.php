
<?php

use function Livewire\Volt\{state, mount, usesFileUploads, with};
use App\Models\SystemConstant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

usesFileUploads();

state([
    'currency_symbol'   => '$',
    'default_currency_code' => 'USD',
    'showClearDataConfirm' => false,
    'website_name'      => '',
    'website_logo'      => null,
    'website_favicon'   => null,
    'time_format'       => 'H:i',
    'date_format'       => 'Y-m-d',
    'timezone'          => 'UTC',
    'company_location'  => '',
    'invoice_prefix'    => 'INV-',
    'sales_order_prefix' => 'SO-',
    'map_center_latitude' => '25.2048',
    'map_center_longitude' => '55.2708',
    'map_zoom_level'    => '10',
    'default_gst_percentage' => '18',
    'default_gst_type' => 'exclusive',
    'default_po_remarks' => '',
    'default_po_terms' => '',
    'selectedWipeModules' => [],
]);

mount(function () {
    if (!auth()->user()->hasRole('Super Admin') && 
        !auth()->user()->can('manage general_settings') &&
        !auth()->user()->can('manage localization_settings')
    ) {
        abort(403);
    }

    // Auto-seed USD if there are no currencies
    if (\App\Models\Currency::count() === 0) {
        \App\Models\Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.0,
            'is_base' => true,
        ]);
    }
    
    $this->currency_symbol     = setting('currency_symbol', '$');
    $this->default_currency_code = setting('default_currency_code', 'USD');
    $this->website_name        = setting('website_name', 'SCM ERP');
    $this->time_format         = setting('time_format', 'H:i');
    $this->date_format         = setting('date_format', 'Y-m-d');
    $this->timezone            = setting('timezone', config('app.timezone', 'UTC'));
    $this->company_location    = setting('company_location', '');
    $this->invoice_prefix      = setting('invoice_prefix', 'INV-');
    $this->sales_order_prefix  = setting('sales_order_prefix', 'SO-');
    $this->map_center_latitude = setting('map_center_latitude', '25.2048');
    $this->map_center_longitude = setting('map_center_longitude', '55.2708');
    $this->map_zoom_level      = setting('map_zoom_level', '10');
    $this->default_gst_percentage = setting('default_gst_percentage', '18');
    $this->default_gst_type       = setting('default_gst_type', 'exclusive');
    $this->default_po_remarks     = setting('default_po_remarks', '');
    $this->default_po_terms       = setting('default_po_terms', '');
});

with(fn () => [
    'gst_percentages' => SystemConstant::where('type', 'gst_percentage')->where('is_active', true)->orderBy('value')->get(),
    'currencies' => \App\Models\Currency::all(),
]);

$saveSettings = function () {
    $user = auth()->user();
    $isSuper = $user->hasRole('Super Admin');

    if ($isSuper || $user->can('manage general_settings')) {
        set_setting('website_name',      $this->website_name);
        set_setting('company_location',  $this->company_location);
        if ($this->website_logo) {
            $path = $this->website_logo->store('logos', 'public');
            set_setting('website_logo', '/storage/' . $path);
        }
        if ($this->website_favicon) {
            $path = $this->website_favicon->store('logos', 'public');
            set_setting('website_favicon', '/storage/' . $path);
        }
    }

    if ($isSuper || $user->can('manage localization_settings')) {
        // Validate timezone
        $tz = $this->timezone;
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $tz = 'UTC';
            $this->timezone = 'UTC';
        }
        
        set_setting('default_currency_code', $this->default_currency_code);
        
        // Find currency
        $selectedCurrency = \App\Models\Currency::where('code', $this->default_currency_code)->first();
        if ($selectedCurrency) {
            // Update currency symbol automatically
            set_setting('currency_symbol', $selectedCurrency->symbol);
            $this->currency_symbol = $selectedCurrency->symbol;
            
            // Set as base currency, reset others
            \App\Models\Currency::where('id', '!=', $selectedCurrency->id)->update(['is_base' => false]);
            $selectedCurrency->update(['is_base' => true, 'exchange_rate' => 1.0]);
        } else {
            set_setting('currency_symbol',   $this->currency_symbol);
        }

        set_setting('time_format',       $this->time_format);
        set_setting('date_format',       $this->date_format);
        set_setting('timezone',          $tz);
        
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
    }

    if ($isSuper || $user->can('manage general_settings')) {
        set_setting('invoice_prefix',    $this->invoice_prefix);
        set_setting('sales_order_prefix',$this->sales_order_prefix);
        set_setting('default_gst_percentage', $this->default_gst_percentage);
        set_setting('default_gst_type',       $this->default_gst_type);
        set_setting('default_po_remarks',     $this->default_po_remarks);
        set_setting('default_po_terms',       $this->default_po_terms);
        set_setting('map_center_latitude',$this->map_center_latitude);
        set_setting('map_center_longitude',$this->map_center_longitude);
        set_setting('map_zoom_level',    $this->map_zoom_level);
    }

    $this->dispatch('toast', type: 'success', message:  'Settings updated successfully.');
};

$clearData = function () {
    if (!auth()->user()->hasRole('Super Admin')) abort(403, 'Only Super Admin can clear data.');

    if (empty($this->selectedWipeModules)) {
        $this->dispatch('toast', type: 'error', message: 'Please select at least one module to wipe.');
        return;
    }

    // Call the dedicated artisan command with force and selected modules
    \Illuminate\Support\Facades\Artisan::call('erp:clear-data', [
        '--force' => true,
        '--modules' => implode(',', $this->selectedWipeModules),
    ]);

    $formatted = array_map(function($m) {
        return ucwords(str_replace('_', ' ', $m));
    }, $this->selectedWipeModules);

    session()->flash('danger_message', 'Operational data for ' . implode(', ', $formatted) . ' has been permanently cleared. Users, roles, and settings have been preserved.');
    $this->selectedWipeModules = [];
    $this->showClearDataConfirm = false;
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('System Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            

            @if (session()->has('danger_message'))
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                    {{ session('danger_message') }}
                </div>
            @endif

            <!-- Settings Tabs -->
            <div x-data="{ tab: '{{ auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage general_settings') ? 'general' : (auth()->user()->can('manage localization_settings') ? 'localization' : 'procurement') }}' }" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-4xl">
                    <section>
                        <header class="flex border-b border-gray-200 mb-6">
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage general_settings'))
                            <button @click="tab = 'general'" :class="{ 'border-indigo-500 text-indigo-600': tab === 'general', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': tab !== 'general' }" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                                General Info
                            </button>
                            @endif
                            
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage localization_settings'))
                            <button @click="tab = 'localization'" :class="{ 'border-indigo-500 text-indigo-600': tab === 'localization', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': tab !== 'localization' }" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                                Localization
                            </button>
                            @endif
                            
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage general_settings'))
                            <button @click="tab = 'procurement'" :class="{ 'border-indigo-500 text-indigo-600': tab === 'procurement', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': tab !== 'procurement' }" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                                System & Procurement
                            </button>
                            @endif
                        </header>

                        <form wire:submit="saveSettings" class="space-y-6">
                            
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage general_settings'))
                            <div x-show="tab === 'general'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Website Name -->
                                <div>
                                    <label for="website_name" class="block text-sm font-medium text-gray-700">Website/App Name</label>
                                    <input wire:model="website_name" id="website_name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                </div>

                                <!-- Website Logo -->
                                <div>
                                    <label for="website_logo" class="block text-sm font-medium text-gray-700">Website Logo</label>
                                    <input wire:model="website_logo" id="website_logo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                    @if(setting('website_logo'))
                                        <div class="mt-2">
                                            <span class="text-xs text-gray-500">Current Logo:</span><br>
                                            <img src="{{ setting('website_logo') }}" class="h-10 mt-1 object-contain" alt="Logo">
                                        </div>
                                    @endif
                                </div>

                                <!-- Website Favicon -->
                                <div>
                                    <label for="website_favicon" class="block text-sm font-medium text-gray-700">Tab Icon (Favicon)</label>
                                    <input wire:model="website_favicon" id="website_favicon" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                    @if(setting('website_favicon'))
                                        <div class="mt-2">
                                            <span class="text-xs text-gray-500">Current Icon:</span><br>
                                            <img src="{{ setting('website_favicon') }}" class="h-8 mt-1 object-contain" alt="Favicon">
                                        </div>
                                    @endif
                                </div>

                                <!-- Company Location -->
                                <div class="md:col-span-2">
                                    <label for="company_location" class="block text-sm font-medium text-gray-700">Company Location / Address</label>
                                    <textarea wire:model="company_location" id="company_location" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>
                            </div>
                            @endif

                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage localization_settings'))
                            <div x-show="tab === 'localization'" style="display: none;" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Default Currency Selection -->
                                <div>
                                    <label for="default_currency_code" class="block text-sm font-medium text-gray-700">Default Base Currency</label>
                                    <select wire:model.live="default_currency_code" id="default_currency_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Currency Symbol -->
                                <div>
                                    <label for="currency_symbol" class="block text-sm font-medium text-gray-700">Currency Symbol (auto-synced)</label>
                                    <input wire:model="currency_symbol" id="currency_symbol" type="text" class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="$" required readonly />
                                </div>

                                <!-- Date Format -->
                                <div>
                                    <label for="date_format" class="block text-sm font-medium text-gray-700">Date Format</label>
                                    <select wire:model="date_format" id="date_format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="Y-m-d">YYYY-MM-DD</option>
                                        <option value="d/m/Y">DD/MM/YYYY</option>
                                        <option value="d-m-Y">DD-MM-YYYY</option>
                                        <option value="m/d/Y">MM/DD/YYYY</option>
                                        <option value="M d, Y">MMM DD, YYYY</option>
                                        <option value="F d, Y">MMMM DD, YYYY</option>
                                    </select>
                                </div>

                                <!-- Time Format -->
                                <div>
                                    <label for="time_format" class="block text-sm font-medium text-gray-700">Time Format</label>
                                    <select wire:model="time_format" id="time_format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="H:i">24-Hour (14:30)</option>
                                        <option value="h:i A">12-Hour (02:30 PM)</option>
                                    </select>
                                </div>

                                <!-- Timezone -->
                                <div class="md:col-span-2">
                                    <label for="timezone" class="block text-sm font-medium text-gray-700">
                                        Timezone
                                        <span class="ml-2 text-xs font-normal text-gray-400">— affects all dates &amp; times site-wide</span>
                                    </label>

                                    {{-- Live clock preview --}}
                                    <div class="mt-1 mb-2 flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Current server time in selected zone:
                                            <strong>{{ now()->timezone($timezone)->format('D, d M Y — H:i:s T') }}</strong>
                                        </span>
                                    </div>

                                    <select wire:model.live="timezone" id="timezone"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">

                                        @php
                                            $allTz   = timezone_identifiers_list();
                                            $grouped = [];
                                            foreach ($allTz as $tz) {
                                                $region = strpos($tz, '/') !== false
                                                    ? explode('/', $tz)[0]
                                                    : 'Other';
                                                $grouped[$region][] = $tz;
                                            }
                                            ksort($grouped);
                                        @endphp

                                        @foreach($grouped as $region => $zones)
                                            <optgroup label="{{ $region }}">
                                                @foreach($zones as $tz)
                                                    <option value="{{ $tz }}" @selected($this->timezone === $tz)>
                                                        {{ str_replace('_', ' ', $tz) }}
                                                        (UTC{{ now()->timezone($tz)->format('P') }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif

                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage general_settings'))
                            <div x-show="tab === 'procurement'" style="display: none;" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Invoice Prefix -->
                                <div>
                                    <label for="invoice_prefix" class="block text-sm font-medium text-gray-700">Invoice Prefix</label>
                                    <input wire:model="invoice_prefix" id="invoice_prefix" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="INV-" />
                                </div>

                                <!-- Sales Order Prefix -->
                                <div>
                                    <label for="sales_order_prefix" class="block text-sm font-medium text-gray-700">Sales Order Prefix</label>
                                    <input wire:model="sales_order_prefix" id="sales_order_prefix" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="SO-" />
                                </div>

                                <!-- Purchase Order Defaults Section Header -->
                                <div class="md:col-span-2 border-t border-gray-150 pt-6 mt-4">
                                    <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-wider">Purchase Order & Tax Defaults</h3>
                                    <p class="text-xs text-gray-400 mt-1">Configure default GST behavior, remarks, and terms for newly created Purchase Orders.</p>
                                </div>

                                <div>
                                    <label for="default_gst_percentage" class="block text-sm font-medium text-gray-700">Default GST Percentage (%)</label>
                                    <select wire:model="default_gst_percentage" id="default_gst_percentage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="0">0%</option>
                                        @foreach($gst_percentages as $gst)
                                            <option value="{{ $gst->value }}">{{ $gst->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Default GST Type -->
                                <div>
                                    <label for="default_gst_type" class="block text-sm font-medium text-gray-700">Default GST Calculation</label>
                                    <select wire:model="default_gst_type" id="default_gst_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="exclusive">Exclusive (Added to Subtotal)</option>
                                        <option value="inclusive">Inclusive (Included in Price)</option>
                                    </select>
                                </div>

                                <!-- Default PO Remarks -->
                                <div class="md:col-span-2">
                                    <label for="default_po_remarks" class="block text-sm font-medium text-gray-700">Default PO Remarks</label>
                                    <textarea wire:model="default_po_remarks" id="default_po_remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g., Deliver between 9 AM and 5 PM"></textarea>
                                </div>

                                <!-- Default PO Terms & Conditions -->
                                <div class="md:col-span-2">
                                    <label for="default_po_terms" class="block text-sm font-medium text-gray-700">Default PO Terms & Conditions</label>
                                    <textarea wire:model="default_po_terms" id="default_po_terms" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g., 1. Payment due in 30 days..."></textarea>
                                </div>

                                <!-- Live Map Configuration Section Header -->
                                <div class="md:col-span-2 border-t border-gray-150 pt-6 mt-4">
                                    <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-wider">Live Fleet Map Configuration</h3>
                                    <p class="text-xs text-gray-400 mt-1">Configure default coordinates and zoom level for the Fleet Dispatch Board live tracking map.</p>
                                </div>

                                <!-- Map Default Latitude -->
                                <div>
                                    <label for="map_center_latitude" class="block text-sm font-medium text-gray-700">Default Center Latitude</label>
                                    <input wire:model="map_center_latitude" id="map_center_latitude" type="number" step="0.0001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono" placeholder="25.2048" required />
                                </div>

                                <!-- Map Default Longitude -->
                                <div>
                                    <label for="map_center_longitude" class="block text-sm font-medium text-gray-700">Default Center Longitude</label>
                                    <input wire:model="map_center_longitude" id="map_center_longitude" type="number" step="0.0001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono" placeholder="55.2708" required />
                                </div>

                                <!-- Map Default Zoom Level -->
                                <div>
                                    <label for="map_zoom_level" class="block text-sm font-medium text-gray-700">Default Zoom Level</label>
                                    <select wire:model="map_zoom_level" id="map_zoom_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-semibold text-gray-700" required>
                                        @for($z = 1; $z <= 19; $z++)
                                            <option value="{{ $z }}">{{ $z }} - {{ $z <= 6 ? 'Global' : ($z <= 12 ? 'City' : 'Street') }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            @endif

                            <div class="flex items-center gap-4 mt-8">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    {{ __('Save Settings') }}
                                </button>
                                <span wire:loading wire:target="saveSettings" class="text-sm text-gray-500">Saving...</span>
                            </div>
                        </form>
                    </section>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg border-l-4 border-red-500">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-red-600">
                                {{ __('Danger Zone: Clear ERP Data') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Select specific modules to wipe operational records from (products, orders, invoices, stock, etc.). Your user accounts, roles, and settings are safe.') }}
                            </p>
                        </header>
 
                        <div class="mt-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="selectedWipeModules" value="sales" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Sales & CRM</span>
                                        <span class="block text-[10px] text-gray-400">Orders, Customers, Leads</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="selectedWipeModules" value="procurement" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Procurement & RFQs</span>
                                        <span class="block text-[10px] text-gray-400">Suppliers, POs, RFQs</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="selectedWipeModules" value="inventory" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Inventory & Stock</span>
                                        <span class="block text-[10px] text-gray-400">Products, Warehouses, Stock</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="selectedWipeModules" value="logistics" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Logistics & Fleet</span>
                                        <span class="block text-[10px] text-gray-400">Vehicles, Drivers, Shipments</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="selectedWipeModules" value="manufacturing" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Manufacturing & QC</span>
                                        <span class="block text-[10px] text-gray-400">MOs, BOMs, Quality Checks</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="selectedWipeModules" value="finance" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Finance & Accounting</span>
                                        <span class="block text-[10px] text-gray-400">Invoices, Expenses, GL Entries</span>
                                    </div>
                                </label>
                            </div>
 
                            <div class="flex items-center gap-3 pt-2">
                                <button type="button" 
                                        wire:click="$set('showClearDataConfirm', true)" 
                                        class="inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                                        @if(empty($selectedWipeModules)) disabled @endif>
                                    {{ __('Clear Selected Data') }}
                                </button>
                                <button type="button" 
                                        wire:click="$set('selectedWipeModules', ['sales', 'procurement', 'inventory', 'logistics', 'manufacturing', 'finance'])"
                                        class="text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                                    Select All Modules
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
 
        </div>
    </div>
 
    <!-- Confirm Modal -->
    @if($showClearDataConfirm)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showClearDataConfirm', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Clear Selected ERP Data</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-3">Are you absolutely sure? This will <strong class="text-red-600">permanently delete</strong> all operational records for the selected modules. This action <strong>cannot be undone</strong>.</p>
                                <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Modules selected for wiping:</p>
                                <ul class="text-xs text-red-600 space-y-0.5 list-disc list-inside font-bold uppercase tracking-wider mb-3">
                                    @foreach($selectedWipeModules as $wipeMod)
                                        <li>{{ ucwords(str_replace('_', ' ', $wipeMod)) }}</li>
                                    @endforeach
                                </ul>
                                <p class="text-xs text-green-700 mt-2">✓ User accounts, roles, permissions, and system settings will <strong>NOT</strong> be deleted.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" wire:click="clearData" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Yes, wipe selected modules
                    </button>
                    <button type="button" wire:click="$set('showClearDataConfirm', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<?php

use App\Models\Shipment;
use App\Models\Driver;
use App\Models\Vehicle;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public function with()
    {
        return [
            // Active shipments to show on the map or assign (case-insensitive)
            'shipments' => Shipment::with(['salesOrder.customer', 'vehicle', 'driver.user'])
                ->whereIn(DB::raw('LOWER(status)'), ['processing', 'dispatched', 'in_transit'])
                ->get(),
            
            // Unassigned shipments for the dispatch board (case-insensitive)
            'unassignedShipments' => Shipment::with(['salesOrder.customer'])
                ->where(DB::raw('LOWER(status)'), 'processing')
                ->whereNull('driver_id')
                ->get(),

            // Online/Active Drivers for the map (case-insensitive, includes legacy 'active' driver statuses too)
            'drivers' => Driver::with(['user', 'vehicle'])
                ->whereIn(DB::raw('LOWER(status)'), ['online', 'on_job', 'active'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get(),
        ];
    }
}; ?>

<div class="relative min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-indigo-50/20 text-slate-800" x-data="dispatchBoard()">
    <!-- Leaflet CSS loaded dynamically -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        <!-- Header Banner -->
        <div class="relative rounded-3xl overflow-hidden mb-8 shadow-xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 p-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-indigo-200/10">
            <div class="z-10 text-center md:text-left">
                <span class="px-3 py-1 text-xs font-semibold bg-indigo-500/20 text-indigo-300 rounded-full border border-indigo-500/30 uppercase tracking-widest">Logistics</span>
                <h1 class="text-4xl font-extrabold text-white mt-3 tracking-tight">Fleet Dispatch Center</h1>
                <p class="text-indigo-200/70 text-sm mt-1 max-w-xl">Live driver GPS tracking, route status visualizer, and intelligent shipment assignments.</p>
            </div>
            <div class="z-10 flex gap-3">
                <a href="{{ url('/logistics/vehicles') }}" class="bg-indigo-600 hover:bg-indigo-750 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-md hover:shadow-lg transition duration-200 flex items-center gap-2 border border-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Go to Command Center
                </a>
            </div>
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Map Area -->
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm overflow-hidden border border-slate-200 flex flex-col h-[600px]">
                <div class="p-6 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 text-sm">Live Dispatch & Telematics Map</h3>
                    <div class="flex gap-4 text-xs font-semibold text-slate-600">
                        <span class="flex items-center"><span class="w-3 h-3 bg-indigo-650 rounded-full mr-1.5 shadow-sm"></span> Active Driver</span>
                        <span class="flex items-center"><span class="w-3 h-3 bg-rose-500 rounded-full mr-1.5 shadow-sm"></span> Destination</span>
                    </div>
                </div>
                <!-- Map Container -->
                <div id="map" class="flex-1 w-full bg-slate-100 z-10 relative"></div>
            </div>

            <!-- Dispatch Sidebar -->
            <div class="flex flex-col gap-6 h-[600px]">
                <!-- Unassigned Shipments -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 flex flex-col flex-1 overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <h3 class="font-bold text-slate-800 text-sm">Unassigned Shipments</h3>
                    </div>
                    <div class="p-6 overflow-y-auto flex-1 bg-slate-50/20 space-y-4">
                        @forelse($unassignedShipments as $shipment)
                            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow transition duration-200">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-extrabold text-sm text-slate-800">SHP-{{ str_pad($shipment->id, 5, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-[10px] font-bold bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">Processing</span>
                                </div>
                                <p class="text-xs text-slate-500 mb-2 font-medium">SO #{{ $shipment->sales_order_id }} &bull; {{ $shipment->salesOrder->customer->name ?? 'Unknown Customer' }}</p>
                                <p class="text-xs text-slate-600 truncate font-semibold mb-3">
                                    <svg class="w-3.5 h-3.5 text-rose-500 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    {{ $shipment->destination_address ?: 'No Address Provided' }}
                                </p>
                                <a href="{{ url('/logistics/shipments') }}" class="block text-center text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold border border-indigo-200 py-2 rounded-xl transition duration-150">
                                    Configure & Assign
                                </a>
                            </div>
                        @empty
                            <div class="text-center text-xs text-slate-400 py-8 font-bold">✓ All shipments assigned!</div>
                        @endforelse
                    </div>
                </div>

                <!-- Active Drivers Status -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden h-[250px] flex flex-col">
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <h3 class="font-bold text-slate-800 text-sm">Active & Online Drivers</h3>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1 space-y-3">
                        @forelse($drivers as $driver)
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-105">
                                <div>
                                    <p class="text-xs font-extrabold text-slate-800">{{ $driver->user->name ?? 'Unknown Driver' }}</p>
                                    <p class="text-[10px] text-slate-500 mt-0.5 font-bold">{{ $driver->vehicle->license_plate ?? 'Unassigned' }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-[9px] font-extrabold rounded-full {{ $driver->status === 'on_job' ? 'bg-amber-50 text-amber-700 border border-amber-250' : 'bg-emerald-50 text-emerald-700 border border-emerald-250' }}">
                                    {{ str_replace('_', ' ', strtoupper($driver->status)) }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center text-xs text-slate-400 py-8 font-bold">No active drivers online.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('dispatchBoard', () => ({
            map: null,
            drivers: @json($drivers),
            shipments: @json($shipments),

            init() {
                // Initialize map using Leaflet
                setTimeout(() => {
                    this.initMap();
                }, 100);
            },

            initMap() {
                // Default center
                const defaultCenter = [{{ setting('map_center_latitude', '25.2048') }}, {{ setting('map_center_longitude', '55.2708') }}];
                const defaultZoom = {{ setting('map_zoom_level', '10') }};

                this.map = L.map('map').setView(defaultCenter, defaultZoom);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                    maxZoom: 19
                }).addTo(this.map);

                // Add Driver Markers
                const driverIcon = L.divIcon({
                    html: `<div class="w-8 h-8 bg-indigo-650 rounded-full border-2 border-white shadow-lg flex items-center justify-center text-white text-xs font-bold animate-pulse">🚚</div>`,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                let bounds = L.latLngBounds();
                let hasMarkers = false;

                this.drivers.forEach(driver => {
                    if (driver.latitude && driver.longitude) {
                        const marker = L.marker([driver.latitude, driver.longitude], {icon: driverIcon})
                            .addTo(this.map)
                            .bindPopup(`<b>${driver.user?.name || 'Driver'}</b><br>Status: ${driver.status}<br>Vehicle: ${driver.vehicle?.license_plate || 'N/A'}`);
                        bounds.extend(marker.getLatLng());
                        hasMarkers = true;
                    }
                });

                // Add Shipment Destinations
                const destIcon = L.divIcon({
                    html: `<div class="w-6 h-6 bg-rose-500 rounded-full border-2 border-white shadow-lg flex items-center justify-center text-white text-[10px] font-bold">📍</div>`,
                    className: '',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });

                this.shipments.forEach(shipment => {
                    if (shipment.dest_lat && shipment.dest_lng) {
                        const marker = L.marker([shipment.dest_lat, shipment.dest_lng], {icon: destIcon})
                            .addTo(this.map)
                            .bindPopup(`<b>SHP-${String(shipment.id).padStart(5, '0')}</b><br>Status: ${shipment.status}`);
                        bounds.extend(marker.getLatLng());
                        hasMarkers = true;
                    }
                });

                if (hasMarkers) {
                    this.map.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        }));
    });
</script>

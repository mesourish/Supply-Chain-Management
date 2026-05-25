<?php

use App\Models\Shipment;
use App\Models\Driver;
use App\Models\Vehicle;
use Livewire\Volt\Component;

new class extends Component {
    public function with()
    {
        return [
            // Active shipments to show on the map or assign
            'shipments' => Shipment::with(['salesOrder.customer', 'vehicle', 'driver.user'])
                ->whereIn('status', ['processing', 'dispatched', 'in_transit'])
                ->get(),
            
            // Unassigned shipments for the dispatch board
            'unassignedShipments' => Shipment::with(['salesOrder.customer'])
                ->where('status', 'processing')
                ->whereNull('driver_id')
                ->get(),

            // Online/Active Drivers for the map
            'drivers' => Driver::with(['user', 'vehicle'])
                ->whereIn('status', ['online', 'on_job'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8" x-data="dispatchBoard()">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Fleet Dispatch Dashboard</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Map Area -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200 flex flex-col h-[600px]">
            <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-700">Live Fleet Map</h3>
                <div class="flex gap-2 text-xs">
                    <span class="flex items-center"><span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span> Driver</span>
                    <span class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-1"></span> Destination</span>
                </div>
            </div>
            <!-- Map Container -->
            <div id="map" class="flex-1 w-full bg-gray-200 z-10 relative"></div>
        </div>

        <!-- Dispatch Sidebar -->
        <div class="flex flex-col gap-6 h-[600px]">
            <!-- Unassigned Shipments -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col flex-1 overflow-hidden">
                <div class="p-4 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-700">Unassigned Shipments</h3>
                </div>
                <div class="p-4 overflow-y-auto flex-1 bg-gray-50/50">
                    @forelse($unassignedShipments as $shipment)
                        <div class="bg-white border rounded p-3 mb-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-sm text-gray-800">SHP-{{ str_pad($shipment->id, 5, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Processing</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">SO #{{ $shipment->sales_order_id }} &bull; {{ $shipment->salesOrder->customer->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-gray-600 truncate"><i class="fas fa-map-marker-alt text-red-400 w-4 text-center"></i> {{ $shipment->destination_address ?: 'No Address Provided' }}</p>
                            <button class="mt-2 w-full text-xs bg-indigo-50 text-indigo-600 border border-indigo-200 py-1 rounded hover:bg-indigo-100 transition-colors">
                                Assign Driver
                            </button>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-500 py-4">All shipments assigned!</div>
                    @endforelse
                </div>
            </div>

            <!-- Active Drivers Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden h-[250px] flex flex-col">
                <div class="p-4 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-700">Active Drivers</h3>
                </div>
                <div class="p-4 overflow-y-auto flex-1">
                    @forelse($drivers as $driver)
                        <div class="flex items-center justify-between py-2 border-b last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $driver->user->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">{{ $driver->vehicle->license_plate ?? 'No Vehicle' }}</p>
                            </div>
                            <span class="px-2 py-1 text-[10px] rounded-full {{ $driver->status === 'on_job' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ str_replace('_', ' ', strtoupper($driver->status)) }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-500 py-4">No drivers online.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

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
                // Default center (can be company HQ)
                // Let's pick a random US center or just use 0,0 if nothing
                const defaultCenter = [37.7749, -122.4194]; // SF as placeholder

                this.map = L.map('map').setView(defaultCenter, 10);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                    maxZoom: 19
                }).addTo(this.map);

                // Add Driver Markers
                const driverIcon = L.divIcon({
                    html: `<div class="w-6 h-6 bg-blue-500 rounded-full border-2 border-white shadow flex items-center justify-center text-white text-xs font-bold"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg></div>`,
                    className: '',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
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
                    html: `<div class="w-4 h-4 bg-red-500 rounded-full border-2 border-white shadow"></div>`,
                    className: '',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
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

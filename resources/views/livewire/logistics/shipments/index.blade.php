<?php

use App\Models\Shipment;
use App\Models\SalesOrder;
use App\Models\Vehicle;
use App\Models\Driver;
use Livewire\Volt\Component;

new class extends Component {
    public $sales_order_id, $vehicle_id, $driver_id, $status = 'processing', $tracking_number;
    public $origin_address, $destination_address, $origin_lat, $origin_lng, $dest_lat, $dest_lng;
    public $isEditing = false;
    public $shipmentId = null;

    public function rules()
    {
        return [
            'sales_order_id' => 'required|exists:sales_orders,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'status' => 'required|in:processing,dispatched,in_transit,delivered,cancelled',
            'tracking_number' => 'nullable|string|max:255',
            'origin_address' => 'nullable|string',
            'destination_address' => 'nullable|string',
            'origin_lat' => 'nullable|numeric|between:-90,90',
            'origin_lng' => 'nullable|numeric|between:-180,180',
            'dest_lat' => 'nullable|numeric|between:-90,90',
            'dest_lng' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function save()
    {
        if ($this->shipmentId && !auth()->user()->can('edit shipments')) abort(403);
        if (!$this->shipmentId && !auth()->user()->can('create shipments')) abort(403);

        $this->validate();

        $so = SalesOrder::find($this->sales_order_id);

        Shipment::updateOrCreate(
            ['id' => $this->shipmentId],
            [
                'sales_order_id' => $this->sales_order_id,
                'vehicle_id' => $this->vehicle_id ?: null,
                'driver_id' => $this->driver_id ?: null,
                'status' => $this->status,
                'tracking_number' => $this->tracking_number,
                'origin_address' => $this->origin_address,
                'destination_address' => $this->destination_address,
                'origin_lat' => $this->origin_lat,
                'origin_lng' => $this->origin_lng,
                'dest_lat' => $this->dest_lat,
                'dest_lng' => $this->dest_lng,
            ]
        );

        $this->resetInputFields();
        $this->dispatch('toast', type: 'success', message: $this->shipmentId ? 'Shipment Updated Successfully.' : 'Shipment Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message: 'Details loaded successfully.');
        if (!auth()->user()->can('edit shipments')) abort(403);
        $shipment = Shipment::findOrFail($id);
        $this->shipmentId = $id;
        $this->sales_order_id = $shipment->sales_order_id;
        $this->vehicle_id = $shipment->vehicle_id;
        $this->driver_id = $shipment->driver_id;
        $this->status = $shipment->status;
        $this->tracking_number = $shipment->tracking_number;
        $this->origin_address = $shipment->origin_address;
        $this->destination_address = $shipment->destination_address;
        $this->origin_lat = $shipment->origin_lat;
        $this->origin_lng = $shipment->origin_lng;
        $this->dest_lat = $shipment->dest_lat;
        $this->dest_lng = $shipment->dest_lng;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete shipments')) abort(403);
        Shipment::find($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Shipment Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->sales_order_id = '';
        $this->vehicle_id = '';
        $this->driver_id = '';
        $this->status = 'processing';
        $this->tracking_number = '';
        $this->origin_address = '';
        $this->destination_address = '';
        $this->origin_lat = null;
        $this->origin_lng = null;
        $this->dest_lat = null;
        $this->dest_lng = null;
        $this->shipmentId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        if (!auth()->user()->can('view shipments')) abort(403);
        return [
            'shipments' => Shipment::with(['salesOrder', 'vehicle', 'driver.user'])->latest()->get(),
            'salesOrders' => SalesOrder::whereIn('status', ['confirmed', 'shipped'])->get(),
            'vehicles' => Vehicle::where('status', '!=', 'maintenance')->get(),
            'drivers' => Driver::with('user')->get(),
        ];
    }
}; ?>

<div class="relative min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-indigo-50/20 text-slate-800" x-data="shipmentMap()">
    <!-- Leaflet CSS loaded dynamically -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        <!-- Header Banner -->
        <div class="relative rounded-3xl overflow-hidden mb-8 shadow-xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 p-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-indigo-200/10">
            <div class="z-10 text-center md:text-left">
                <span class="px-3 py-1 text-xs font-semibold bg-indigo-500/20 text-indigo-300 rounded-full border border-indigo-500/30 uppercase tracking-widest">Logistics</span>
                <h1 class="text-4xl font-extrabold text-white mt-3 tracking-tight">Shipment Control Center</h1>
                <p class="text-indigo-200/70 text-sm mt-1 max-w-xl">Create customer shipments, manage active delivery routes, and track fleet IoT telemetry in real-time.</p>
            </div>
            <div class="z-10 flex gap-3">
                <a href="{{ url('/logistics/vehicles') }}" class="bg-indigo-600 hover:bg-indigo-750 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-md hover:shadow-lg transition duration-200 flex items-center gap-2 border border-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                    Go to Command Center
                </a>
            </div>
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Side: Save/Edit Form -->
            <div class="lg:col-span-1 bg-white border border-slate-200 shadow-sm rounded-3xl p-6 h-fit">
                <h2 class="text-base font-bold text-slate-800 mb-6 border-b border-slate-100 pb-2 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    {{ $isEditing ? 'Edit Shipment File' : 'Initialize New Shipment' }}
                </h2>

                <form wire:submit="save" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Sales Order *</label>
                        <select wire:model="sales_order_id" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50" required>
                            <option value="">Select Confirmed SO</option>
                            @foreach($salesOrders as $so)
                                <option value="{{ $so->id }}">SO #{{ $so->id }} - {{ strtoupper($so->status) }}</option>
                            @endforeach
                        </select>
                        @error('sales_order_id') <span class="text-rose-500 text-[10px] mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Assigned Vehicle</label>
                        <select wire:model="vehicle_id" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50">
                            <option value="">Unassigned / Pending</option>
                            @foreach($vehicles as $veh)
                                <option value="{{ $veh->id }}">{{ $veh->license_plate }} ({{ $veh->type }} - {{ $veh->brand }})</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <span class="text-rose-500 text-[10px] mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Assigned Driver</label>
                        <select wire:model="driver_id" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50">
                            <option value="">Unassigned / Pending</option>
                            @foreach($drivers as $dr)
                                <option value="{{ $dr->id }}">{{ $dr->user->name ?? 'Unknown' }} - {{ $dr->license_number }}</option>
                            @endforeach
                        </select>
                        @error('driver_id') <span class="text-rose-500 text-[10px] mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Shipment Status *</label>
                        <select wire:model="status" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50" required>
                            <option value="processing">Processing</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        @error('status') <span class="text-rose-500 text-[10px] mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Tracking Number</label>
                        <input wire:model="tracking_number" type="text" placeholder="e.g. TRK-98319-X" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50">
                        @error('tracking_number') <span class="text-rose-500 text-[10px] mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                            Routing Information
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-450 uppercase tracking-widest mb-1">Origin Point</label>
                                <textarea wire:model="origin_address" rows="2" placeholder="Origin address details" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50"></textarea>
                                <div class="flex gap-2 mt-2">
                                    <input wire:model="origin_lat" type="number" step="0.000001" placeholder="Lat" class="block w-full rounded-xl border-slate-200 shadow-sm text-xs bg-slate-50/50">
                                    <input wire:model="origin_lng" type="number" step="0.000001" placeholder="Lng" class="block w-full rounded-xl border-slate-200 shadow-sm text-xs bg-slate-50/50">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-450 uppercase tracking-widest mb-1">Destination Point</label>
                                <textarea wire:model="destination_address" rows="2" placeholder="Destination address details" class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50"></textarea>
                                <div class="flex gap-2 mt-2">
                                    <input wire:model="dest_lat" type="number" step="0.000001" placeholder="Lat" class="block w-full rounded-xl border-slate-200 shadow-sm text-xs bg-slate-50/50">
                                    <input wire:model="dest_lng" type="number" step="0.000001" placeholder="Lng" class="block w-full rounded-xl border-slate-200 shadow-sm text-xs bg-slate-50/50">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-750 text-white py-2.5 rounded-xl text-xs font-bold shadow-sm transition duration-150">
                            {{ $isEditing ? 'Update Shipment' : 'Save Shipment' }}
                        </button>
                        @if($isEditing)
                            <button type="button" wire:click="resetInputFields" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-bold transition duration-150">
                                Cancel
                            </button>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Right Side: Shipments List -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white border border-slate-200 shadow-sm rounded-3xl overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-base font-bold text-slate-800">Shipments Directory</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest">Shipment ID / Tracking</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest">Sales Order</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest">Driver & Vehicle</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-right text-[10px] font-bold text-slate-500 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                                @forelse($shipments as $shipment)
                                    <tr class="hover:bg-slate-50/30 transition duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-extrabold text-slate-800">SHP-{{ str_pad($shipment->id, 5, '0', STR_PAD_LEFT) }}</div>
                                            <div class="text-[10px] text-slate-500 font-bold mt-0.5">{{ $shipment->tracking_number ?? 'No Tracking Code' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-650 font-bold">
                                            SO #{{ $shipment->sales_order_id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-850">
                                                {{ $shipment->driver->user->name ?? 'Unassigned Driver' }}
                                            </div>
                                            <div class="text-[10px] text-slate-550 mt-0.5 font-semibold">
                                                {{ $shipment->vehicle->license_plate ?? 'No Vehicle Assigned' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 text-[9px] font-extrabold rounded-full border 
                                                {{ in_array($shipment->status, ['delivered']) ? 'bg-emerald-50 text-emerald-700 border-emerald-250' : 
                                                   (in_array($shipment->status, ['processing', 'cancelled']) ? 'bg-amber-50 text-amber-700 border-amber-250' : 
                                                   'bg-indigo-50 text-indigo-700 border-indigo-250') }}">
                                                {{ strtoupper(str_replace('_', ' ', $shipment->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-bold">
                                            @can('edit shipments')
                                                <button wire:click="edit({{ $shipment->id }})" class="text-indigo-650 hover:text-indigo-850 mr-3 transition duration-150">Edit</button>
                                            @endcan
                                            @can('delete shipments')
                                                <button wire:click="delete({{ $shipment->id }})" wire:confirm="Are you sure you want to delete this shipment record?" class="text-rose-600 hover:text-rose-800 transition duration-150">Delete</button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-xs text-slate-400 font-bold">
                                            No shipment logs matching configuration tags.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Map View -->
                <div class="bg-white border border-slate-200 shadow-sm rounded-3xl p-6 overflow-hidden">
                    <h2 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        Route Optimization & Live IoT Tracking
                    </h2>
                    <div id="shipments-map" wire:ignore class="w-full h-96 rounded-2xl shadow-sm border border-slate-200 z-0 bg-slate-50"></div>
                </div>
            </div>
        </div>

    {{-- Scripts inside root div: Leaflet + Alpine shipmentMap component --}}
    @once
    @push('scripts')
    @endpush
    @endonce
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('shipmentMap', () => ({
                map: null,
                init() {
                    setTimeout(() => {
                        this.initMap();
                    }, 200);
                },
                initMap() {
                    if (this.map) return; // prevent double-init
                    this.map = L.map('shipments-map').setView([25.2048, 55.2708], 9);
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
                    }).addTo(this.map);

                    let shipments = @json($shipments);
                    let bounds = [];

                    const originIcon = L.divIcon({
                        html: `<div class="w-5 h-5 bg-indigo-650 rounded-full border-2 border-white shadow flex items-center justify-center text-white text-[9px] font-bold">📤</div>`,
                        className: '',
                        iconSize: [20, 20]
                    });

                    const destIcon = L.divIcon({
                        html: `<div class="w-5 h-5 bg-rose-500 rounded-full border-2 border-white shadow flex items-center justify-center text-white text-[9px] font-bold">📍</div>`,
                        className: '',
                        iconSize: [20, 20]
                    });

                    shipments.forEach((s) => {
                        if (s.origin_lat && s.origin_lng) {
                            L.marker([s.origin_lat, s.origin_lng], {icon: originIcon}).bindPopup('<b>Origin:</b> ' + s.origin_address).addTo(this.map);
                            bounds.push([s.origin_lat, s.origin_lng]);
                        }
                        if (s.dest_lat && s.dest_lng) {
                            L.marker([s.dest_lat, s.dest_lng], {icon: destIcon}).bindPopup('<b>Destination:</b> ' + s.destination_address).addTo(this.map);
                            bounds.push([s.dest_lat, s.dest_lng]);
                        }
                        if (s.origin_lat && s.dest_lat) {
                            L.polyline([[s.origin_lat, s.origin_lng], [s.dest_lat, s.dest_lng]], {
                                color: '#4f46e5',
                                weight: 3,
                                opacity: 0.6,
                                dashArray: '5, 10'
                            }).addTo(this.map);
                        }

                        // IoT Real-Time Simulation
                        if (s.origin_lat && s.dest_lat && s.status === 'in_transit') {
                            let truckIcon = L.divIcon({
                                className: 'bg-transparent',
                                html: '<div class="w-8 h-8 bg-indigo-600 rounded-full border-2 border-white shadow-lg flex items-center justify-center text-white text-xs font-bold">🚚</div>'
                            });
                            let movingMarker = L.marker([s.origin_lat, s.origin_lng], {icon: truckIcon}).addTo(this.map);
                            movingMarker.bindPopup('<b>Live IoT Tracking:</b> Vehicle ' + (s.vehicle?.license_plate || 'Unassigned'));

                            // Animate towards destination
                            let progress = 0;
                            setInterval(() => {
                                progress += 0.005;
                                if (progress >= 1) progress = 0;
                                let lat = s.origin_lat + (s.dest_lat - s.origin_lat) * progress;
                                let lng = s.origin_lng + (s.dest_lng - s.origin_lng) * progress;
                                movingMarker.setLatLng([lat, lng]);
                            }, 300);
                        }
                    });

                    if (bounds.length > 0) {
                        this.map.fitBounds(bounds, { padding: [40, 40] });
                    }
                }
            }));
        });
    </script>
</div>


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
        session()->flash('message', $this->shipmentId ? 'Shipment Updated Successfully.' : 'Shipment Created Successfully.');
    }

    public function edit($id)
    {
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
        session()->flash('message', 'Shipment Deleted Successfully.');
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

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Shipment' : 'Create Shipment' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sales Order *</label>
                        <select wire:model="sales_order_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">Select SO</option>
                            @foreach($salesOrders as $so)
                                <option value="{{ $so->id }}">SO #{{ $so->id }} - {{ $so->status }}</option>
                            @endforeach
                        </select>
                        @error('sales_order_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                        <select wire:model="vehicle_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Unassigned</option>
                            @foreach($vehicles as $veh)
                                <option value="{{ $veh->id }}">{{ $veh->license_plate }} ({{ $veh->type }})</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Driver</label>
                        <select wire:model="driver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Unassigned</option>
                            @foreach($drivers as $dr)
                                <option value="{{ $dr->id }}">{{ $dr->user->name ?? 'Unknown' }} - {{ $dr->license_number }}</option>
                            @endforeach
                        </select>
                        @error('driver_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status *</label>
                        <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="processing">Processing</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tracking Number</label>
                        <input wire:model="tracking_number" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('tracking_number') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Logistics / Map Data -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2 border-b pb-2">Routing Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Origin Address</label>
                            <textarea wire:model="origin_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            <div class="flex gap-2 mt-2">
                                <input wire:model="origin_lat" type="number" step="0.00000001" placeholder="Lat" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <input wire:model="origin_lng" type="number" step="0.00000001" placeholder="Lng" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Destination Address</label>
                            <textarea wire:model="destination_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            <div class="flex gap-2 mt-2">
                                <input wire:model="dest_lat" type="number" step="0.00000001" placeholder="Lat" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <input wire:model="dest_lng" type="number" step="0.00000001" placeholder="Lng" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium">
                        {{ $isEditing ? 'Update Shipment' : 'Save Shipment' }}
                    </button>
                    @if($isEditing)
                        <button type="button" wire:click="resetInputFields" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 text-sm font-medium">Cancel</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Shipments List</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shipment ID / Track#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver / Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($shipments as $shipment)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">SHP-{{ str_pad($shipment->id, 5, '0', STR_PAD_LEFT) }}</div>
                                    <div class="text-xs text-gray-500">{{ $shipment->tracking_number ?? 'No Tracking' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">SO #{{ $shipment->sales_order_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $shipment->driver->user->name ?? 'Unassigned' }}
                                    <br><span class="text-xs text-gray-500">{{ $shipment->vehicle->license_plate ?? '' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ in_array($shipment->status, ['delivered']) ? 'bg-green-100 text-green-800' : (in_array($shipment->status, ['processing', 'cancelled']) ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                                        {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @can('edit shipments')
                                    <button wire:click="edit({{ $shipment->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @endcan
                                    @can('delete shipments')
                                    <button wire:click="delete({{ $shipment->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:text-red-900">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No shipments found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

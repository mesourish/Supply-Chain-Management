<?php

use App\Models\Vehicle;
use Livewire\Volt\Component;

new class extends Component {
    public $license_plate, $type, $capacity, $status = 'available', $latitude, $longitude;
    public $isEditing = false;
    public $vehicleId = null;

    public function rules()
    {
        return [
            'license_plate' => 'required|string|max:255|unique:vehicles,license_plate,' . $this->vehicleId,
            'type' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:available,maintenance,active',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function save()
    {
        if ($this->vehicleId && !auth()->user()->can('edit vehicles')) abort(403);
        if (!$this->vehicleId && !auth()->user()->can('create vehicles')) abort(403);

        $this->validate();

        if ($this->vehicleId) {
            Vehicle::findOrFail($this->vehicleId)->update([
                'license_plate' => $this->license_plate,
                'type' => $this->type,
                'capacity' => $this->capacity,
                'status' => $this->status,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ]);
        } else {
            Vehicle::create([
                'license_plate' => $this->license_plate,
                'type' => $this->type,
                'capacity' => $this->capacity,
                'status' => $this->status,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ]);
        }

        $this->resetInputFields();
        session()->flash('message', $this->vehicleId ? 'Vehicle Updated Successfully.' : 'Vehicle Created Successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('edit vehicles')) abort(403);
        $vehicle = Vehicle::findOrFail($id);
        $this->vehicleId = $id;
        $this->license_plate = $vehicle->license_plate;
        $this->type = $vehicle->type;
        $this->capacity = $vehicle->capacity;
        $this->status = $vehicle->status;
        $this->latitude = $vehicle->latitude;
        $this->longitude = $vehicle->longitude;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete vehicles')) abort(403);
        Vehicle::find($id)->delete();
        session()->flash('message', 'Vehicle Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->license_plate = '';
        $this->type = '';
        $this->capacity = null;
        $this->status = 'available';
        $this->latitude = null;
        $this->longitude = null;
        $this->vehicleId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        if (!auth()->user()->can('view vehicles')) abort(403);
        return [
            'vehicles' => Vehicle::latest()->get(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Vehicle' : 'Create Vehicle' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">License Plate *</label>
                        <input wire:model="license_plate" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        @error('license_plate') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type (Truck, Van) *</label>
                        <input wire:model="type" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        @error('type') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Capacity (kg/lbs)</label>
                        <input wire:model="capacity" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('capacity') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status *</label>
                        <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="available">Available</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="active">Active</option>
                        </select>
                        @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Latitude (for tracking)</label>
                        <input wire:model="latitude" type="number" step="0.00000001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('latitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Longitude (for tracking)</label>
                        <input wire:model="longitude" type="number" step="0.00000001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('longitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium">
                        {{ $isEditing ? 'Update Vehicle' : 'Save Vehicle' }}
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
            <h2 class="text-2xl font-semibold mb-4">Vehicles List</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License Plate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type / Capacity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($vehicles as $vehicle)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $vehicle->license_plate }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $vehicle->type }}
                                    @if($vehicle->capacity) <br><span class="text-xs">Cap: {{ $vehicle->capacity }}</span> @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $vehicle->status === 'available' ? 'bg-green-100 text-green-800' : ($vehicle->status === 'active' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                        {{ ucfirst($vehicle->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @can('edit vehicles')
                                    <button wire:click="edit({{ $vehicle->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @endcan
                                    @can('delete vehicles')
                                    <button wire:click="delete({{ $vehicle->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:text-red-900">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No vehicles found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

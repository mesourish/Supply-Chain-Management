<?php

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Volt\Component;

new class extends Component {
    public $user_id, $license_number, $status = 'offline', $latitude, $longitude, $current_vehicle_id;
    public $isEditing = false;
    public $driverId = null;

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'license_number' => 'required|string|max:255|unique:drivers,license_number,' . $this->driverId,
            'status' => 'required|in:offline,online,on_job',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'current_vehicle_id' => 'nullable|exists:vehicles,id',
        ];
    }

    public function save()
    {
        if ($this->driverId && !auth()->user()->can('edit drivers')) abort(403);
        if (!$this->driverId && !auth()->user()->can('create drivers')) abort(403);

        $this->validate();

        if ($this->driverId) {
            Driver::findOrFail($this->driverId)->update([
                'user_id' => $this->user_id,
                'license_number' => $this->license_number,
                'status' => $this->status,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'current_vehicle_id' => $this->current_vehicle_id ?: null,
            ]);
        } else {
            Driver::create([
                'user_id' => $this->user_id,
                'license_number' => $this->license_number,
                'status' => $this->status,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'current_vehicle_id' => $this->current_vehicle_id ?: null,
            ]);
        }

        $this->resetInputFields();
        session()->flash('message', $this->driverId ? 'Driver Updated Successfully.' : 'Driver Created Successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('edit drivers')) abort(403);
        $driver = Driver::findOrFail($id);
        $this->driverId = $id;
        $this->user_id = $driver->user_id;
        $this->license_number = $driver->license_number;
        $this->status = $driver->status;
        $this->latitude = $driver->latitude;
        $this->longitude = $driver->longitude;
        $this->current_vehicle_id = $driver->current_vehicle_id;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete drivers')) abort(403);
        Driver::find($id)->delete();
        session()->flash('message', 'Driver Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->user_id = '';
        $this->license_number = '';
        $this->status = 'offline';
        $this->latitude = null;
        $this->longitude = null;
        $this->current_vehicle_id = null;
        $this->driverId = null;
        $this->isEditing = false;
    }

    public function with()
    {
        if (!auth()->user()->can('view drivers')) abort(403);
        $hasDriverRole = \Spatie\Permission\Models\Role::where('name', 'Driver')->exists();
        return [
            'drivers' => Driver::with(['user', 'vehicle'])->latest()->get(),
            'users' => $hasDriverRole ? User::role('Driver')->get() : User::all(),
            'vehicles' => Vehicle::all(),
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Driver' : 'Create Driver' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">User Account *</label>
                        <select wire:model="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">Select User</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Note: The user must have a 'Driver' role.</p>
                        @error('user_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">License Number *</label>
                        <input wire:model="license_number" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        @error('license_number') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status *</label>
                        <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                            <option value="on_job">On Job</option>
                        </select>
                        @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Current Vehicle (Assigned)</label>
                        <select wire:model="current_vehicle_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">None</option>
                            @foreach($vehicles as $veh)
                                <option value="{{ $veh->id }}">{{ $veh->license_plate }} ({{ $veh->type }})</option>
                            @endforeach
                        </select>
                        @error('current_vehicle_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Latitude (Live)</label>
                        <input wire:model="latitude" type="number" step="0.00000001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('latitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Longitude (Live)</label>
                        <input wire:model="longitude" type="number" step="0.00000001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('longitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium">
                        {{ $isEditing ? 'Update Driver' : 'Save Driver' }}
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
            <h2 class="text-2xl font-semibold mb-4">Drivers List</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status & Vehicle</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($drivers as $driver)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $driver->user->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $driver->license_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $driver->status === 'online' ? 'bg-blue-100 text-blue-800' : ($driver->status === 'on_job' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ ucfirst($driver->status) }}
                                    </span>
                                    @if($driver->current_vehicle_id)
                                        <div class="text-xs text-gray-500 mt-1">Veh: {{ $driver->vehicle->license_plate ?? 'N/A' }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @can('edit drivers')
                                    <button wire:click="edit({{ $driver->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @endcan
                                    @can('delete drivers')
                                    <button wire:click="delete({{ $driver->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:text-red-900">Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No drivers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

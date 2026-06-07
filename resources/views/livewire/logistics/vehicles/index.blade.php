<?php

use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\User;
use App\Models\Project;
use App\Models\PredictiveMaintenance;
use App\Models\FuelAnomaly;
use App\Models\FleetExpense;
use App\Models\VehicleDamageAudit;
use App\Models\EquipmentUsageCharge;
use App\Models\FleetMarketplaceTransfer;
use Livewire\Volt\Component;

new class extends Component {
    // Current UI State
    public $activeTab = 'command-center'; // command-center, digital-twin, drivers, finance, projects, qr-damage
    public $selectedVehicleId = null;
    public $selectedDriverId = null;

    // CRUD Fields for Vehicle (legacy compatibility & new fields)
    public $license_plate, $type, $capacity, $status = 'available', $latitude, $longitude;
    public $brand, $model, $year, $fuel_type, $purchase_cost, $purchase_date, $lifecycle_stage = 'active';
    public $carbon_emissions = 150.0;
    public $qr_code_token;
    public $isEditing = false;
    public $vehicleId = null;

    // Equipment Billing state
    public $billVehicleId, $billProjectId, $billHours = 8, $billRate = 75;
    
    // Fleet Marketplace state
    public $transferVehicleId, $transferFromProjectId, $transferToProjectId;

    // Handover Damage Audit state
    public $auditVehicleId, $auditDriverId, $beforeScratches = 0, $afterScratches = 0, $beforeDents = 0, $afterDents = 0, $beforeNotes = '', $afterNotes = '', $auditStatus = 'logged';

    // Route Optimization simulated states
    public $selectedRoute = 'A'; // A or B

    public function mount()
    {
        if (!auth()->user()->can('view vehicles')) abort(403);
        
        // Read URL query parameter
        $this->activeTab = request()->query('tab', 'command-center');

        // Default select first vehicle for Digital Twin
        $firstVeh = Vehicle::first();
        if ($firstVeh) {
            $this->selectedVehicleId = $firstVeh->id;
            $this->billVehicleId = $firstVeh->id;
            $this->transferVehicleId = $firstVeh->id;
            $this->auditVehicleId = $firstVeh->id;
        }

        $firstDrv = Driver::first();
        if ($firstDrv) {
            $this->selectedDriverId = $firstDrv->id;
            $this->auditDriverId = $firstDrv->id;
        }

        $firstProj = Project::first();
        if ($firstProj) {
            $this->billProjectId = $firstProj->id;
            $this->transferFromProjectId = $firstProj->id;
            $this->transferToProjectId = $firstProj->id;
        }

    }

    public function rules()
    {
        return [
            'license_plate' => 'required|string|max:255|unique:vehicles,license_plate,' . $this->vehicleId,
            'type' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:available,maintenance,active',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1900|max:2100',
            'fuel_type' => 'nullable|string',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'lifecycle_stage' => 'required|in:purchase,registration,active,maintenance,depreciation,resale,disposed',
            'carbon_emissions' => 'nullable|numeric|min:0',
        ];
    }

    public function save()
    {
        if ($this->vehicleId && !auth()->user()->can('edit vehicles')) abort(403);
        if (!$this->vehicleId && !auth()->user()->can('create vehicles')) abort(403);

        $this->validate();

        // Calculate a simulated health score based on type and age
        $age = $this->year ? (date('Y') - $this->year) : 2;
        $health = max(35, 100 - ($age * 6) - ($this->status === 'maintenance' ? 30 : 0));
        $risk = $health > 80 ? 'low' : ($health > 60 ? 'medium' : 'high');

        $digitalTwin = [
            'engine' => $health > 70 ? 'optimal' : 'service_required',
            'battery' => $health > 60 ? 'good' : 'replace_soon',
            'tyres' => $health > 75 ? 'optimal' : 'worn',
            'fuel_system' => $health > 65 ? 'good' : 'leak_detected',
            'gps' => 'active',
            'insurance' => 'valid_until_' . date('Y-m-d', strtotime('+6 months'))
        ];

        $data = [
            'license_plate' => $this->license_plate,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'latitude' => $this->latitude ?: 25.2048,
            'longitude' => $this->longitude ?: 55.2708,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'fuel_type' => $this->fuel_type ?: 'Diesel',
            'purchase_cost' => $this->purchase_cost,
            'purchase_date' => $this->purchase_date,
            'lifecycle_stage' => $this->lifecycle_stage,
            'health_score' => $health,
            'risk_level' => $risk,
            'digital_twin_status' => $digitalTwin,
            'carbon_emissions' => $this->carbon_emissions ?: 160.0,
            'qr_code_token' => $this->qr_code_token ?: 'QR-' . strtoupper(Str::random(10)),
        ];

        $wasEditing = (bool)$this->vehicleId;

        if ($this->vehicleId) {
            Vehicle::findOrFail($this->vehicleId)->update($data);
        } else {
            Vehicle::create($data);
        }

        $this->resetInputFields();
        $this->dispatch('close-create-modal');
        $this->dispatch('toast', type: 'success', message: $wasEditing ? 'Vehicle Updated Successfully.' : 'Vehicle Created Successfully.');
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
        $this->brand = $vehicle->brand;
        $this->model = $vehicle->model;
        $this->year = $vehicle->year;
        $this->fuel_type = $vehicle->fuel_type;
        $this->purchase_cost = $vehicle->purchase_cost;
        $this->purchase_date = $vehicle->purchase_date;
        $this->lifecycle_stage = $vehicle->lifecycle_stage;
        $this->carbon_emissions = $vehicle->carbon_emissions;
        $this->qr_code_token = $vehicle->qr_code_token;
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('delete vehicles')) abort(403);
        Vehicle::find($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Vehicle Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->license_plate = '';
        $this->type = '';
        $this->capacity = null;
        $this->status = 'available';
        $this->latitude = null;
        $this->longitude = null;
        $this->brand = '';
        $this->model = '';
        $this->year = null;
        $this->fuel_type = 'Diesel';
        $this->purchase_cost = null;
        $this->purchase_date = null;
        $this->lifecycle_stage = 'active';
        $this->carbon_emissions = 150.0;
        $this->qr_code_token = '';
        $this->vehicleId = null;
        $this->isEditing = false;
    }

    // Advanced Fleet Functions
    public function selectVehicle($id)
    {
        $this->selectedVehicleId = $id;
        $this->billVehicleId = $id;
        $this->transferVehicleId = $id;
        $this->auditVehicleId = $id;
    }

    public function selectDriver($id)
    {
        $this->selectedDriverId = $id;
        $this->auditDriverId = $id;
    }

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
    }

    // 1. Submit Equipment Usage Billing
    public function submitEquipmentBilling()
    {
        if (!auth()->user()->can('manage equipment_billing')) abort(403);
        $vehicle = Vehicle::findOrFail($this->billVehicleId);
        $total = $this->billHours * $this->billRate;

        EquipmentUsageCharge::create([
            'vehicle_id' => $this->billVehicleId,
            'project_id' => $this->billProjectId,
            'usage_hours' => $this->billHours,
            'hourly_rate' => $this->billRate,
            'total_charge' => $total,
            'billing_date' => date('Y-m-d'),
            'status' => 'pending'
        ]);

        // Automatically log a project-specific fleet expense to link cost to project
        FleetExpense::create([
            'vehicle_id' => $this->billVehicleId,
            'category' => 'repairs', // internal machine utilization charge
            'amount' => $total,
            'date_incurred' => date('Y-m-d'),
            'project_id' => $this->billProjectId
        ]);

        $this->dispatch('toast', type: 'success', message: 'Equipment billing logged. total: $' . number_format($total, 2));
    }

    // 2. Post Billing to Project Ledger
    public function postToProjectLedger($chargeId)
    {
        if (!auth()->user()->can('manage equipment_billing')) abort(403);
        $charge = EquipmentUsageCharge::findOrFail($chargeId);
        $charge->update(['status' => 'posted']);
        $this->dispatch('toast', type: 'success', message: 'Charge Posted successfully to project ledger.');
    }

    // 3. Submit Fleet Marketplace Transfer Request
    public function submitMarketplaceTransfer()
    {
        if (!auth()->user()->can('manage marketplace_transfers')) abort(403);
        if ($this->transferFromProjectId == $this->transferToProjectId) {
            $this->dispatch('toast', type: 'error', message: 'Source and destination projects must be different.');
            return;
        }

        FleetMarketplaceTransfer::create([
            'vehicle_id' => $this->transferVehicleId,
            'from_project_id' => $this->transferFromProjectId,
            'to_project_id' => $this->transferToProjectId,
            'request_date' => date('Y-m-d'),
            'status' => 'requested'
        ]);

        $this->dispatch('toast', type: 'success', message: 'Transfer request submitted on internal Fleet Marketplace.');
    }

    public function updateTransferStatus($transferId, $status)
    {
        if (!auth()->user()->can('manage marketplace_transfers')) abort(403);
        $transfer = FleetMarketplaceTransfer::findOrFail($transferId);
        $transfer->update(['status' => $status]);
        
        if ($status === 'approved') {
            $this->dispatch('toast', type: 'success', message: 'Transfer Approved! Re-routing vehicle.');
        } else {
            $this->dispatch('toast', type: 'success', message: 'Transfer status updated to: ' . strtoupper($status));
        }
    }

    // 4. Submit Handover Damage Audit
    public function submitDamageAudit()
    {
        if (!auth()->user()->can('manage damage_audits')) abort(403);
        VehicleDamageAudit::create([
            'vehicle_id' => $this->auditVehicleId,
            'driver_id' => $this->auditDriverId,
            'audit_date' => date('Y-m-d'),
            'before_trip_scratches' => $this->beforeScratches,
            'after_trip_scratches' => $this->afterScratches,
            'before_trip_dents' => $this->beforeDents,
            'after_trip_dents' => $this->afterDents,
            'before_trip_notes' => $this->beforeNotes,
            'after_trip_notes' => $this->afterNotes,
            'status' => ($this->afterScratches > $this->beforeScratches || $this->afterDents > $this->beforeDents) ? 'review_pending' : 'logged'
        ]);

        $this->dispatch('toast', type: 'success', message: 'Vehicle handover damage audit submitted.');
        
        $this->beforeScratches = 0; $this->afterScratches = 0;
        $this->beforeDents = 0; $this->afterDents = 0;
        $this->beforeNotes = ''; $this->afterNotes = '';
    }

    // 5. Smart Driver Auto Assignment Simulation
    public function assignDriverAutomatically($vehicleId)
    {
        // Auto-select nearest driver with lowest workload (offline/online and without vehicle)
        $vehicle = Vehicle::findOrFail($vehicleId);
        $driver = Driver::where('status', 'online')
            ->whereNull('current_vehicle_id')
            ->orderBy('safety_score', 'desc')
            ->first();

        if ($driver) {
            $driver->update([
                'current_vehicle_id' => $vehicle->id,
                'status' => 'on_job'
            ]);
            $vehicle->update(['status' => 'active']);
            $this->dispatch('toast', type: 'success', message: 'Smart dispatch: Driver ' . $driver->user->name . ' auto-assigned to ' . $vehicle->license_plate);
        } else {
            $this->dispatch('toast', type: 'error', message: 'No available online drivers without active assignments.');
        }
    }



    public function with()
    {
        $selectedVehicle = $this->selectedVehicleId ? Vehicle::with(['predictiveMaintenances', 'fuelAnomalies', 'fleetExpenses', 'damageAudits'])->find($this->selectedVehicleId) : null;
        $selectedDriver = $this->selectedDriverId ? Driver::with('user')->find($this->selectedDriverId) : null;

        return [
            'vehicles' => Vehicle::latest()->get(),
            'drivers' => Driver::with(['user', 'vehicle'])->latest()->get(),
            'projects' => Project::all(),
            'predictiveMaintenances' => PredictiveMaintenance::with('vehicle')->latest()->get(),
            'fuelAnomalies' => FuelAnomaly::with('vehicle')->latest()->get(),
            'fleetExpenses' => FleetExpense::with(['vehicle', 'project'])->latest()->get(),
            'damageAudits' => VehicleDamageAudit::with(['vehicle', 'driver.user'])->latest()->get(),
            'equipmentUsageCharges' => EquipmentUsageCharge::with(['vehicle', 'project'])->latest()->get(),
            'marketplaceTransfers' => FleetMarketplaceTransfer::with(['vehicle', 'fromProject', 'toProject'])->latest()->get(),
            'selectedVehicle' => $selectedVehicle,
            'selectedDriver' => $selectedDriver,
            'users' => \Spatie\Permission\Models\Role::where('name', 'Driver')->exists() ? User::role('Driver')->get() : User::all(),
        ];
    }
}; ?>

<div class="relative min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-indigo-50/20 text-slate-800"
     x-data="{
         loading: true,
         progress: 0,
         currentTab: '{{ $activeTab }}',
         init() {
             let interval = setInterval(() => {
                 this.progress += 20;
                 if (this.progress >= 100) {
                     clearInterval(interval);
                     setTimeout(() => { this.loading = false; }, 200);
                 }
             }, 150);
         }
     }">

    <!-- Leaflet CSS loaded dynamically -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- 1. SPARK SPLASH INITIALIZATION SCREEN -->
    <div x-show="loading"
         class="fixed inset-0 bg-slate-900 z-[9999] flex flex-col items-center justify-center text-center p-6 transition-all duration-300"
         x-transition:leave="opacity-0 scale-95">
        
        <!-- Animated telemetry radar icon -->
        <div class="relative w-24 h-24 mb-6">
            <div class="absolute inset-0 rounded-full border-4 border-indigo-500/20 animate-ping"></div>
            <div class="absolute inset-2 rounded-full border-4 border-indigo-500/40 animate-pulse"></div>
            <div class="absolute inset-4 rounded-full border-4 border-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/50">
                <svg class="w-8 h-8 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
        </div>

        <h2 class="text-xl font-extrabold text-white tracking-widest uppercase">Initializing Telematics Link</h2>
        <p class="text-xs text-indigo-400 mt-1 font-bold">SCM ERP SECURE FLEET PROTOCOL v4.8</p>

        <!-- Dynamic loader subtext -->
        <div class="h-6 mt-4">
            <template x-if="progress < 40">
                <span class="text-[10px] text-slate-400 uppercase tracking-widest animate-pulse">Establishing handshake with GPS gateway...</span>
            </template>
            <template x-if="progress >= 40 && progress < 80">
                <span class="text-[10px] text-slate-300 uppercase tracking-widest animate-pulse">Reading OBD2 engine codes & emissions index...</span>
            </template>
            <template x-if="progress >= 80">
                <span class="text-[10px] text-indigo-300 uppercase tracking-widest animate-pulse">Syncing Driver safety scores & project costs...</span>
            </template>
        </div>

        <!-- Custom high-tech progress bar -->
        <div class="w-64 h-1.5 bg-slate-800 rounded-full overflow-hidden mt-2 border border-slate-700/50">
            <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-400 transition-all duration-150"
                 :style="`width: ${progress}%`"></div>
        </div>
        <span class="text-slate-400 text-[10px] mt-2 font-bold" x-text="`${progress}%`"></span>
    </div>

    <!-- 2. MAIN PREMIUM LIGHT DASHBOARD VIEW -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8" x-show="!loading" x-cloak>
        
        <!-- Header Banner with light-themed gradient styling -->
        <div class="relative rounded-3xl overflow-hidden mb-8 shadow-xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 p-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-indigo-200/10">
            <div class="z-10 text-center md:text-left">
                <span class="px-3 py-1 text-xs font-semibold bg-indigo-500/20 text-indigo-300 rounded-full border border-indigo-500/30 uppercase tracking-widest">Fleet & Logistics</span>
                <h1 class="text-4xl font-extrabold text-white mt-3 tracking-tight">Fleet Command Center</h1>
                <p class="text-indigo-200/70 text-sm mt-1 max-w-xl">Intelligent predictive maintenance, Digital Twin monitoring, driver scoring, and construction equipment project ledger charges.</p>
            </div>
            <div class="z-10 flex gap-3">
                <button wire:click="resetInputFields" @click="$dispatch('open-create-modal')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-md hover:shadow-lg transition duration-200 flex items-center gap-2 border border-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Add Fleet Asset
                </button>
            </div>
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl"></div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex flex-wrap gap-2 mb-8 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm">
            <button wire:click="selectTab('command-center')" @click="currentTab = 'command-center'" :class="currentTab === 'command-center' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50'" class="px-4 py-2.5 text-xs font-bold rounded-xl transition duration-200 flex items-center gap-2">
                Command Center
            </button>
            <button wire:click="selectTab('digital-twin')" @click="currentTab = 'digital-twin'" :class="currentTab === 'digital-twin' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50'" class="px-4 py-2.5 text-xs font-bold rounded-xl transition duration-200 flex items-center gap-2">
                Digital Twin & Lifecycle
            </button>
            <button wire:click="selectTab('drivers')" @click="currentTab = 'drivers'" :class="currentTab === 'drivers' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50'" class="px-4 py-2.5 text-xs font-bold rounded-xl transition duration-200 flex items-center gap-2">
                Driver Intelligence
            </button>
            <button wire:click="selectTab('finance')" @click="currentTab = 'finance'" :class="currentTab === 'finance' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50'" class="px-4 py-2.5 text-xs font-bold rounded-xl transition duration-200 flex items-center gap-2">
                Expenses & Profitability
            </button>
            <button wire:click="selectTab('projects')" @click="currentTab = 'projects'" :class="currentTab === 'projects' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50'" class="px-4 py-2.5 text-xs font-bold rounded-xl transition duration-200 flex items-center gap-2">
                Equipment Billing
            </button>
            <button wire:click="selectTab('qr-damage')" @click="currentTab = 'qr-damage'" :class="currentTab === 'qr-damage' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50'" class="px-4 py-2.5 text-xs font-bold rounded-xl transition duration-200 flex items-center gap-2">
                QR & Damage Audits
            </button>
        </div>

        <!-- Active Tab content -->
        <div class="space-y-8">

            <!-- 1. COMMAND CENTER TAB -->
            <div x-show="currentTab === 'command-center'" class="space-y-8" x-transition>
                <!-- Operational Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-white border border-slate-200 p-6 rounded-3xl shadow-sm flex items-center justify-between hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase">Fleet Health Avg</span>
                            <h3 class="text-3xl font-black text-slate-800 mt-1">{{ round($vehicles->avg('health_score') ?: 100) }}%</h3>
                            <p class="text-[10px] text-emerald-600 mt-1">✓ Stable Risk Profile</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 border border-indigo-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 p-6 rounded-3xl shadow-sm flex items-center justify-between hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase">Vehicles In Operation</span>
                            <h3 class="text-3xl font-black text-slate-800 mt-1">{{ $vehicles->where('status', 'active')->count() }} / {{ $vehicles->count() }}</h3>
                            <p class="text-[10px] text-slate-500 mt-1">{{ $vehicles->where('status', 'maintenance')->count() }} in maintenance shop</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 border border-indigo-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 p-6 rounded-3xl shadow-sm flex items-center justify-between hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase">Predictive Alarms</span>
                            <h3 class="text-3xl font-black text-rose-600 mt-1">{{ $predictiveMaintenances->where('status', 'active')->count() }}</h3>
                            <p class="text-[10px] text-rose-500 mt-1">High breakdown risk detected</p>
                        </div>
                        <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-600 border border-rose-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 p-6 rounded-3xl shadow-sm flex items-center justify-between hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase">Fuel Theft Warnings</span>
                            <h3 class="text-3xl font-black text-amber-600 mt-1">{{ $fuelAnomalies->count() }}</h3>
                            <p class="text-[10px] text-amber-500 mt-1 font-bold">Discrepancies logged</p>
                        </div>
                        <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 border border-amber-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Map and Alerts Panels -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Leaflet Live Map -->
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-3xl shadow-sm flex flex-col h-[500px] overflow-hidden">
                        <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">Live Dispatch & Telematics Map</h3>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-250 font-bold">GPS Streaming</span>
                        </div>
                        <div id="fleet-map" class="flex-1 w-full bg-slate-100 z-10" x-data="fleetMap()"></div>
                    </div>

                    <!-- Live Command Center Alerts and Quick Dispatches -->
                    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 flex flex-col h-[500px]">
                        <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Logistics Dispatch Board</h3>
                        
                        <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                            @foreach($vehicles->where('status', 'available') as $veh)
                                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl flex flex-col justify-between hover:border-indigo-500/50 hover:bg-white transition duration-150 shadow-sm">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <span class="text-xs font-bold text-slate-800">{{ $veh->license_plate }}</span>
                                            <p class="text-[10px] text-slate-500 mt-0.5">{{ $veh->brand }} {{ $veh->model }} ({{ $veh->type }})</p>
                                        </div>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-green-50 text-green-700 border border-green-200">Available</span>
                                    </div>
                                    <div class="mt-4 flex justify-between items-center">
                                        <span class="text-[10px] text-slate-500 font-bold">Cap: {{ $veh->capacity }}kg</span>
                                        <button wire:click="assignDriverAutomatically({{ $veh->id }})" class="text-[10px] bg-indigo-650 hover:bg-indigo-750 text-white font-bold py-1 px-3 rounded-lg shadow-sm transition">
                                            Auto Dispatch Driver
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. DIGITAL TWIN & LIFECYCLE TAB -->
            <div x-show="currentTab === 'digital-twin'" class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-transition>
                <!-- Left Panel: Fleet Asset List -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm h-[650px] flex flex-col">
                    <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Active Fleet Assets</h3>
                    <div class="flex-1 overflow-y-auto space-y-3">
                        @foreach($vehicles as $veh)
                            <button wire:click="selectVehicle({{ $veh->id }})" class="w-full text-left p-4 rounded-2xl border {{ $selectedVehicleId === $veh->id ? 'bg-indigo-50/50 border-indigo-500 shadow-sm' : 'bg-slate-50/30 border-slate-200' }} hover:border-indigo-400 transition flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-xs text-slate-800 block">{{ $veh->license_plate }}</span>
                                    <span class="text-[10px] text-slate-500">{{ $veh->brand }} {{ $veh->model }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-black {{ $veh->health_score > 80 ? 'text-green-600' : ($veh->health_score > 60 ? 'text-amber-600' : 'text-rose-600') }}">
                                        Health: {{ $veh->health_score }}%
                                    </span>
                                    <span class="block text-[8px] text-slate-400 mt-0.5">Stage: {{ strtoupper($veh->lifecycle_stage) }}</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Right Panel: Virtual Digital Twin Visualizer -->
                <div class="lg:col-span-2 space-y-8">
                    @if($selectedVehicle)
                        <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                            <div class="flex justify-between items-start border-b border-slate-100 pb-4 mb-6">
                                <div>
                                    <h3 class="text-xl font-bold text-slate-850">Digital Twin: {{ $selectedVehicle->license_plate }}</h3>
                                    <p class="text-xs text-indigo-600 font-bold mb-2">{{ $selectedVehicle->brand }} {{ $selectedVehicle->model }} &bull; Lifecycle: {{ strtoupper($selectedVehicle->lifecycle_stage) }}</p>
                                    <div class="flex items-center gap-2 mt-2">
                                        @can('edit vehicles')
                                            <button wire:click="edit({{ $selectedVehicle->id }})" @click="$dispatch('open-create-modal')" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold rounded-xl text-xs transition">
                                                Edit Asset
                                            </button>
                                        @endcan
                                        @can('delete vehicles')
                                            <button onclick="confirm('Are you sure you want to delete this fleet asset?') || event.stopImmediatePropagation()" wire:click="delete({{ $selectedVehicle->id }})" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl text-xs transition">
                                                Delete Asset
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-slate-550 font-bold">Carbon Emission</span>
                                    <h4 class="text-lg font-black text-indigo-600">{{ $selectedVehicle->carbon_emissions }} g/km</h4>
                                </div>
                            </div>

                            <!-- Twin Visual tree breakdown -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <!-- SVG vehicle blueprint with component highlights -->
                                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 flex flex-col items-center justify-center relative min-h-[300px] shadow-inner">
                                    <span class="absolute top-3 left-3 text-[9px] text-slate-500 uppercase tracking-widest font-black">Live Telematics Diagnostics</span>
                                    
                                    <svg class="w-64 h-32 text-indigo-600/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M2 10h4l2-6 3 14 3-10 2 5h6M4 14h16" />
                                    </svg>
                                    
                                    <div class="grid grid-cols-2 gap-4 w-full mt-6 text-xs border-t border-slate-200 pt-4">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full {{ ($selectedVehicle->digital_twin_status['engine'] ?? 'optimal') === 'optimal' ? 'bg-green-500' : 'bg-red-500 animate-pulse' }}"></span>
                                            <span class="text-slate-600 font-semibold">Engine: {{ strtoupper($selectedVehicle->digital_twin_status['engine'] ?? 'optimal') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full {{ ($selectedVehicle->digital_twin_status['battery'] ?? 'good') === 'good' ? 'bg-green-500' : 'bg-yellow-500 animate-pulse' }}"></span>
                                            <span class="text-slate-600 font-semibold">Battery: {{ strtoupper($selectedVehicle->digital_twin_status['battery'] ?? 'good') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full {{ ($selectedVehicle->digital_twin_status['tyres'] ?? 'optimal') === 'optimal' ? 'bg-green-500' : 'bg-yellow-500 animate-pulse' }}"></span>
                                            <span class="text-slate-600 font-semibold">Tyres: {{ strtoupper($selectedVehicle->digital_twin_status['tyres'] ?? 'optimal') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                                            <span class="text-slate-600 font-semibold">GPS Link: ONLINE</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Predictions and Maintenance Cost Estimates -->
                                <div class="space-y-6">
                                    <!-- AI health card -->
                                    <div class="bg-indigo-50/50 border border-indigo-150 p-6 rounded-2xl flex flex-col justify-between shadow-sm">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="text-xs font-bold text-slate-500 uppercase">Fleet Health Score</span>
                                                <h4 class="text-4xl font-black text-slate-800 mt-1">{{ $selectedVehicle->health_score }}/100</h4>
                                            </div>
                                            <span class="px-3 py-1 text-[10px] font-black rounded-lg uppercase tracking-wide
                                                {{ $selectedVehicle->risk_level === 'low' ? 'bg-green-100 text-green-800 border border-green-200' : ($selectedVehicle->risk_level === 'medium' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : 'bg-red-100 text-red-800 border border-red-200') }}">
                                                Risk: {{ $selectedVehicle->risk_level }}
                                            </span>
                                        </div>

                                        <div class="mt-4 space-y-2 text-xs text-slate-600">
                                            <p class="font-extrabold text-slate-700 border-b border-indigo-100 pb-1.5 mb-2">Health Recommendations:</p>
                                            @if($selectedVehicle->health_score < 75)
                                                <p class="flex items-center gap-1.5"><span class="text-red-500">⚠️</span> Oil degradation detected. Schedule change.</p>
                                                <p class="flex items-center gap-1.5"><span class="text-yellow-600">⚡</span> Battery charging cycle variance of 15% logged.</p>
                                            @else
                                                <p class="flex items-center gap-1.5"><span class="text-green-600">✓</span> Telemetry status is normal. No action required.</p>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Predictive Maintenance engine -->
                                    <div class="bg-slate-50 border border-slate-200 p-6 rounded-2xl space-y-4 shadow-sm">
                                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Predictive Failure Analysis</h4>
                                        
                                        @php 
                                            $prediction = $selectedVehicle->predictiveMaintenances->first(); 
                                        @endphp

                                        @if($prediction)
                                            <div class="border-l-4 border-red-500 bg-red-50/50 p-4 rounded-r-lg space-y-2 text-xs">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-extrabold text-slate-800">Likely Issue: {{ $prediction->likely_issue }}</span>
                                                    <span class="font-bold text-red-700 bg-red-100 px-2 py-0.5 rounded text-[10px]">{{ $prediction->confidence_score }}% Conf.</span>
                                                </div>
                                                <p class="text-slate-600">Failure window: Within next **{{ $prediction->prediction_days_range }} days**.</p>
                                                <p class="text-slate-500 text-[10px]">Based on vibration patterns and operational hours.</p>
                                            </div>
                                        @else
                                            <p class="text-xs text-slate-400 italic">No future maintenance failures predicted for this vehicle.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Lifecycle timeline -->
                            <div class="mt-8 border-t border-slate-100 pt-6">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4">Asset Lifecycle Management</h4>
                                <div class="grid grid-cols-7 gap-2 text-center text-[10px] font-bold">
                                    @foreach(['purchase', 'registration', 'active', 'maintenance', 'depreciation', 'resale', 'disposed'] as $stage)
                                        <div class="p-2.5 rounded-xl border {{ $selectedVehicle->lifecycle_stage === $stage ? 'bg-indigo-650 text-white border-indigo-600 shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                                            {{ strtoupper($stage) }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-slate-500 py-8 bg-white border border-slate-200 rounded-3xl">
                            Select a vehicle to open its virtual Digital Twin dashboard.
                        </div>
                    @endif
                </div>
            </div>

            <!-- 3. DRIVER PERFORMANCE INTELLIGENCE TAB -->
            <div x-show="currentTab === 'drivers'" class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-transition>
                <!-- Left panel: Driver list -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm h-[650px] flex flex-col">
                    <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Drivers performance database</h3>
                    <div class="flex-1 overflow-y-auto space-y-3">
                        @foreach($drivers as $drv)
                            <button wire:click="selectDriver({{ $drv->id }})" class="w-full text-left p-4 rounded-2xl border {{ $selectedDriverId === $drv->id ? 'bg-indigo-50/50 border-indigo-500' : 'bg-slate-50/30 border-slate-200' }} hover:border-indigo-400 transition flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-xs text-slate-800 block">{{ $drv->user->name ?? 'Unknown' }}</span>
                                    <span class="text-[10px] text-slate-500">License: {{ $drv->license_number }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 text-[9px] font-black bg-indigo-600 text-white rounded">
                                        Rating: {{ $drv->overall_rating }}
                                    </span>
                                    <span class="block text-[8px] text-slate-400 mt-1">Safety Score: {{ $drv->safety_score }}%</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Right panel: Performance scorecard -->
                <div class="lg:col-span-2">
                    @if($selectedDriver)
                        <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm space-y-8">
                            <div class="flex justify-between items-start border-b border-slate-100 pb-4">
                                <div>
                                    <h3 class="text-xl font-bold text-slate-800">{{ $selectedDriver->user->name ?? 'Unknown' }}</h3>
                                    <p class="text-xs text-indigo-650 font-bold">Status: {{ strtoupper($selectedDriver->status) }} | Vehicle: {{ $selectedDriver->vehicle->license_plate ?? 'None' }}</p>
                                </div>
                                <span class="text-4xl font-black text-indigo-600">{{ $selectedDriver->overall_rating }}</span>
                            </div>

                            <!-- Scores grid -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl text-center space-y-2">
                                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest block">Safety Score</span>
                                    <h4 class="text-3xl font-black text-slate-800">{{ $selectedDriver->safety_score }}%</h4>
                                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                        <div class="bg-green-500 h-full" style="width: {{ $selectedDriver->safety_score }}%"></div>
                                    </div>
                                </div>
                                
                                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl text-center space-y-2">
                                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest block">Fuel Efficiency</span>
                                    <h4 class="text-3xl font-black text-slate-800">{{ $selectedDriver->fuel_efficiency_score }}%</h4>
                                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                        <div class="bg-indigo-600 h-full" style="width: {{ $selectedDriver->fuel_efficiency_score }}%"></div>
                                    </div>
                                </div>

                                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl text-center space-y-2">
                                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest block">Attendance</span>
                                    <h4 class="text-3xl font-black text-slate-800">{{ $selectedDriver->attendance_score }}%</h4>
                                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                        <div class="bg-yellow-500 h-full" style="width: {{ $selectedDriver->attendance_score }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rewards and Penalties KPI logs -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="bg-emerald-50 border border-emerald-150 p-6 rounded-2xl space-y-4">
                                    <div class="flex justify-between items-center">
                                        <h4 class="font-bold text-green-700 text-sm">Driver Rewards Log</h4>
                                        <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 border border-green-200 rounded-lg">{{ $selectedDriver->rewards_count }} Logged</span>
                                    </div>
                                    <div class="text-xs text-slate-600 space-y-2 font-semibold">
                                        <p>• +5 Pts: Exceptional fuel efficiency rating recorded in Q1.</p>
                                        <p>• +10 Pts: 90 days continuous safe operation on Riyadh route.</p>
                                    </div>
                                </div>

                                <div class="bg-rose-50 border border-rose-150 p-6 rounded-2xl space-y-4">
                                    <div class="flex justify-between items-center">
                                        <h4 class="font-bold text-rose-700 text-sm">Violations & Penalties</h4>
                                        <span class="px-2 py-0.5 text-xs bg-rose-100 text-rose-700 border border-rose-200 rounded-lg">{{ $selectedDriver->penalties_count }} Violations</span>
                                    </div>
                                    <div class="text-xs text-slate-600 space-y-2 font-semibold">
                                        @if($selectedDriver->penalties_count > 0)
                                            <p>• -3 Pts: Idle time exceedance of 45 minutes logged at site.</p>
                                            <p>• -5 Pts: Harsh braking event logged by telemetry sensor.</p>
                                        @else
                                            <p class="text-slate-400 italic">No safety violations logged on record.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-slate-500 py-8 bg-white border border-slate-200 rounded-3xl">
                            Select a driver from the performance database to open scorecard details.
                        </div>
                    @endif
                </div>
            </div>

            <!-- 4. EXPENSES & PROFITABILITY TAB -->
            <div x-show="currentTab === 'finance'" class="space-y-8" x-transition>
                <!-- Top statistics: ROI and Fuel theft alerts -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Vehicle Profitability Dashboard (ROI) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-6">
                        <h3 class="font-bold text-slate-800 text-base border-b border-slate-100 pb-2">Vehicle Profitability & ROI</h3>
                        
                        <div class="space-y-4">
                            <div class="bg-slate-50 p-4 rounded-xl flex justify-between items-center border border-slate-150">
                                <div>
                                    <span class="text-xs text-slate-500">Total Operational Revenue</span>
                                    <h4 class="text-2xl font-black text-slate-800 mt-1">$150,000.00</h4>
                                </div>
                                <span class="text-xs text-emerald-700 font-bold bg-green-50 px-2.5 py-1 rounded border border-green-200">Project Chargebacks</span>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-xl flex justify-between items-center border border-slate-150">
                                <div>
                                    <span class="text-xs text-slate-500">Fleet Operations Cost</span>
                                    <h4 class="text-2xl font-black text-slate-800 mt-1">${{ number_format($fleetExpenses->sum('amount'), 2) }}</h4>
                                </div>
                                <span class="text-xs text-rose-700 font-bold bg-rose-50 px-2.5 py-1 rounded border border-rose-200">Direct Cost</span>
                            </div>
                            <div class="bg-indigo-50/50 border border-indigo-150 p-4 rounded-xl flex justify-between items-center shadow-sm">
                                <div>
                                    <span class="text-xs text-indigo-650 font-bold">Net Profit / ROI Ratio</span>
                                    <h4 class="text-2xl font-black text-slate-800 mt-1">${{ number_format(150000 - $fleetExpenses->sum('amount'), 2) }}</h4>
                                </div>
                                <span class="text-xs text-white font-extrabold bg-indigo-600 px-3 py-1 rounded-lg shadow-sm">45.3% ROI</span>
                            </div>
                        </div>
                    </div>

                    <!-- Fuel Theft Detection anomalies -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-4">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                            <h3 class="font-bold text-slate-800 text-base">Fuel Theft Detection Alerts</h3>
                            <span class="text-[10px] bg-amber-100 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-black animate-pulse">Telemetry Scan</span>
                        </div>

                        <div class="space-y-3 overflow-y-auto max-h-[220px] pr-1 text-xs">
                            @forelse($fuelAnomalies as $anomaly)
                                <div class="p-3 bg-rose-50 border border-rose-150 rounded-xl space-y-1.5">
                                    <div class="flex justify-between font-bold">
                                        <span class="text-slate-850">{{ $anomaly->vehicle->license_plate }}</span>
                                        <span class="text-rose-700">Theft Variance: {{ $anomaly->variance }}L</span>
                                    </div>
                                    <p class="text-slate-500 text-[10px] font-bold">{{ $anomaly->notes }} &bull; {{ $anomaly->anomaly_date }}</p>
                                </div>
                            @empty
                                <p class="text-slate-400 italic py-4 text-center">No fuel theft anomalies or variances detected.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- AI Route Optimization Simulated Panel -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-6">
                        <h3 class="font-bold text-slate-800 text-base border-b border-slate-100 pb-2">AI Route Optimization</h3>
                        
                        <div class="space-y-4">
                            <div class="flex gap-2 p-1 bg-slate-100 rounded-lg text-xs font-bold border border-slate-200">
                                <button wire:click="$set('selectedRoute', 'A')" class="flex-1 py-1.5 rounded {{ $selectedRoute === 'A' ? 'bg-white text-indigo-650 shadow-sm' : 'text-slate-500' }}">Route A (Recommended)</button>
                                <button wire:click="$set('selectedRoute', 'B')" class="flex-1 py-1.5 rounded {{ $selectedRoute === 'B' ? 'bg-white text-indigo-650 shadow-sm' : 'text-slate-500' }}">Route B</button>
                            </div>

                            @if($selectedRoute === 'A')
                                <div class="p-4 bg-emerald-50 border border-emerald-150 rounded-xl space-y-2 text-xs shadow-sm">
                                    <div class="flex justify-between font-extrabold text-slate-800">
                                        <span>Distance: 78 KM</span>
                                        <span class="text-emerald-700">Savings: $12 Fuel</span>
                                    </div>
                                    <p class="text-slate-600">Optimized for Jebel Ali port traffic bypass. Saves **35 minutes** vs route B.</p>
                                </div>
                            @else
                                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
                                    <div class="flex justify-between font-extrabold text-slate-800">
                                        <span>Distance: 92 KM</span>
                                        <span class="text-slate-500">No active savings</span>
                                    </div>
                                    <p class="text-slate-600">Route passes through central toll zones. Higher congestion delays.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Expense Ledger Table -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                    <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Fleet Expense Ledger</h3>
                    <div class="overflow-x-auto text-xs">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Vehicle Plate</th>
                                    <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Expense Category</th>
                                    <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Project Allocation</th>
                                    <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-150">
                                @forelse($fleetExpenses as $expense)
                                    <tr class="hover:bg-slate-50/40">
                                        <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800">{{ $expense->vehicle->license_plate }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-slate-600">{{ strtoupper($expense->category) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-indigo-600 font-extrabold">{{ $expense->project->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap font-black text-slate-800">${{ number_format($expense->amount, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-slate-500 font-semibold">{{ $expense->date_incurred }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-6 py-4 text-center text-slate-400">No fleet expenses logged.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 5. EQUIPMENT BILLING & FLEET MARKETPLACE TAB -->
            <div x-show="currentTab === 'projects'" class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-transition>
                <!-- Left panel: Equipment utilization billing generator -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-6">
                    <h3 class="font-bold text-slate-800 text-base border-b border-slate-100 pb-2">Equipment utilization billing</h3>
                    
                    <form wire:submit.prevent="submitEquipmentBilling" class="space-y-4 text-xs">
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Select Machinery/Asset</label>
                            <select wire:model="billVehicleId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                @foreach($vehicles as $veh)
                                    <option value="{{ $veh->id }}">{{ $veh->license_plate }} &bull; {{ $veh->brand }} {{ $veh->model }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Select Construction Project</label>
                            <select wire:model="billProjectId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                @foreach($projects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-500 mb-1 font-bold">Usage Hours</label>
                                <input wire:model="billHours" type="number" step="0.5" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                            </div>
                            <div>
                                <label class="block text-slate-500 mb-1 font-bold">Hourly rate ($)</label>
                                <input wire:model="billRate" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-750 text-white font-bold py-2.5 rounded-lg shadow transition">
                            Log Equipment Usage Bill
                        </button>
                    </form>

                    <!-- Inner Marketplace vehicle transfers -->
                    <div class="pt-6 border-t border-slate-100 space-y-4">
                        <h4 class="font-bold text-slate-800 text-sm">Fleet internal Marketplace</h4>
                        <p class="text-[10px] text-slate-500">Request idle machinery/vehicles transfers between active project sites.</p>

                        <form wire:submit.prevent="submitMarketplaceTransfer" class="space-y-3 text-xs">
                            <div>
                                <label class="block text-slate-500 mb-1 font-bold">Asset to request</label>
                                <select wire:model="transferVehicleId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                    @foreach($vehicles as $veh)
                                        <option value="{{ $veh->id }}">{{ $veh->license_plate }} (Health: {{ $veh->health_score }}%)</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-slate-500 mb-0.5 font-bold">From Project</label>
                                    <select wire:model="transferFromProjectId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-1.5 font-semibold">
                                        @foreach($projects as $proj)
                                            <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-slate-500 mb-0.5 font-bold">To Project</label>
                                    <select wire:model="transferToProjectId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-1.5 font-semibold">
                                        @foreach($projects as $proj)
                                            <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-650 font-bold py-2 rounded-lg transition shadow-sm">
                                Submit Transfer request
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right panel: Active Charges Ledger and Marketplace Requests -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Charges Ledger -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                        <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Machinery project billing ledger</h3>
                        
                        <div class="overflow-x-auto text-xs">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Asset Plate</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Project</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Hours</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Total charge</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Billing Status</th>
                                        <th class="px-6 py-3 text-right font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-150">
                                    @forelse($equipmentUsageCharges as $charge)
                                        <tr class="hover:bg-slate-50/40">
                                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800">{{ $charge->vehicle->license_plate }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-indigo-600 font-extrabold">{{ $charge->project->name ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-semibold">{{ $charge->usage_hours }} Hrs</td>
                                            <td class="px-6 py-4 whitespace-nowrap font-black text-slate-800">${{ number_format($charge->total_charge, 2) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold
                                                    {{ $charge->status === 'posted' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-yellow-100 text-yellow-755 border border-yellow-200' }}">
                                                    {{ strtoupper($charge->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                @if($charge->status === 'pending')
                                                    <button wire:click="postToProjectLedger({{ $charge->id }})" class="bg-indigo-600 hover:bg-indigo-750 text-white font-bold py-1 px-3 rounded-lg text-[10px] transition shadow-sm">
                                                        Post Ledger
                                                    </button>
                                                @else
                                                    <span class="text-slate-400 text-[10px] font-bold">Posted</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-6 py-4 text-center text-slate-400">No machinery billing charges logged.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Marketplace transfers queue -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                        <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Active marketplace transfers queue</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @forelse($marketplaceTransfers as $trans)
                                <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl flex flex-col justify-between hover:border-slate-350 transition shadow-sm">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="text-xs font-bold text-slate-800">{{ $trans->vehicle->license_plate }}</span>
                                            <p class="text-[9px] text-slate-500 font-bold mt-0.5">From: {{ $trans->fromProject->name }} &bull; To: {{ $trans->toProject->name }}</p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[8px] font-bold
                                            {{ $trans->status === 'approved' ? 'bg-green-100 text-green-700 border border-green-200' : ($trans->status === 'requested' ? 'bg-yellow-100 text-yellow-755 border border-yellow-200' : 'bg-rose-100 text-rose-700 border border-rose-200') }}">
                                            {{ strtoupper($trans->status) }}
                                        </span>
                                    </div>
                                    @if($trans->status === 'requested')
                                        <div class="mt-4 flex gap-2 justify-end">
                                            <button wire:click="updateTransferStatus({{ $trans->id }}, 'rejected')" class="bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 font-bold py-1 px-3 rounded-lg text-[9px] transition">Reject</button>
                                            <button wire:click="updateTransferStatus({{ $trans->id }}, 'approved')" class="bg-green-50 hover:bg-green-100 border border-green-200 text-green-755 font-bold py-1 px-3 rounded-lg text-[9px] transition">Approve</button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-slate-400 text-xs italic py-4 text-center col-span-2">No active marketplace transfer proposals.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. QR-BASED VEHICLE OPERATIONS & DAMAGE AUDITS TAB -->
            <div x-show="currentTab === 'qr-damage'" class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-transition>
                <!-- Left panel: Handover damage check form -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-6">
                    <h3 class="font-bold text-slate-800 text-base border-b border-slate-100 pb-2">Vehicle handover damage audit</h3>
                    
                    <form wire:submit.prevent="submitDamageAudit" class="space-y-4 text-xs">
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Machinery / Vehicle</label>
                            <select wire:model="auditVehicleId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                @foreach($vehicles as $veh)
                                    <option value="{{ $veh->id }}">{{ $veh->license_plate }} &bull; {{ $veh->brand }} {{ $veh->model }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Driver performing Handover</label>
                            <select wire:model="auditDriverId" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                @foreach($drivers as $drv)
                                    <option value="{{ $drv->id }}">{{ $drv->user->name ?? 'Unknown' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="border-t border-slate-100 pt-4 space-y-3">
                            <span class="font-bold text-slate-800 block text-xs">Before Trip checklist</span>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-slate-500 mb-0.5">Scratches count</label>
                                    <input wire:model="beforeScratches" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-1.5">
                                </div>
                                <div>
                                    <label class="block text-slate-500 mb-0.5">Dents count</label>
                                    <input wire:model="beforeDents" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-1.5">
                                </div>
                            </div>
                            <input wire:model="beforeNotes" type="text" placeholder="Notes (e.g. clean, window dusty)" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2">
                        </div>

                        <div class="border-t border-slate-100 pt-4 space-y-3">
                            <span class="font-bold text-slate-800 block text-xs">After Trip checklist</span>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-slate-500 mb-0.5">Scratches count</label>
                                    <input wire:model="afterScratches" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-1.5">
                                </div>
                                <div>
                                    <label class="block text-slate-500 mb-0.5">Dents count</label>
                                    <input wire:model="afterDents" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-1.5">
                                </div>
                            </div>
                            <input wire:model="afterNotes" type="text" placeholder="Notes (e.g. dent on right door, scratches on bumper)" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2">
                        </div>

                        <button type="submit" class="w-full bg-indigo-650 hover:bg-indigo-750 text-white font-bold py-2.5 rounded-lg shadow-sm transition">
                            Log Handover check
                        </button>
                    </form>
                </div>

                <!-- Right panel: Damage logs list & QR scan simulation -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- QR scan simulator -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm flex flex-col md:flex-row gap-8 items-center">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 shadow-inner flex flex-col items-center justify-center shrink-0">
                            <!-- Abstract CSS generated QR code mockup -->
                            <div class="w-32 h-32 bg-slate-900 p-2 flex flex-wrap gap-1 rounded border border-slate-800">
                                @for($i=0; $i<16; $i++)
                                    <div class="w-6 h-6 border {{ $i % 3 === 0 ? 'bg-white border-slate-900' : 'bg-slate-900 border-white' }} rounded-sm"></div>
                                @endfor
                            </div>
                            <span class="text-[10px] font-black text-slate-600 mt-2">QR: VEH-CMD-091</span>
                        </div>

                        <div class="space-y-3">
                            <h3 class="text-base font-bold text-slate-800">QR-Based Handover operations</h3>
                            <p class="text-xs text-slate-500">Scan QR codes printed on vehicle doors or dashboards to instantly perform fast logging actions: log fuel, submit damage audit checks, upload insurance policies, or assign drivers directly in the warehouse.</p>
                            
                            <div class="flex flex-wrap gap-2 text-[10px] font-black">
                                <button @click="$dispatch('toast', {type: 'success', message: 'Simulated QR Scan: Insurance policy PDF uploaded.'})" class="bg-slate-50 border border-slate-200 hover:border-slate-400 text-slate-600 py-1.5 px-3 rounded-lg">Upload Docs</button>
                                <button @click="$dispatch('toast', {type: 'success', message: 'Simulated QR Scan: Add 60 Litres Diesel logged.'})" class="bg-slate-50 border border-slate-200 hover:border-slate-400 text-slate-600 py-1.5 px-3 rounded-lg">Quick Log Fuel</button>
                                <button @click="$dispatch('toast', {type: 'success', message: 'Simulated QR Scan: Maintenance scheduled.'})" class="bg-slate-50 border border-slate-200 hover:border-slate-400 text-slate-600 py-1.5 px-3 rounded-lg">Log Maintenance</button>
                            </div>
                        </div>
                    </div>

                    <!-- Damage audits ledger -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                        <h3 class="font-bold text-slate-800 text-base mb-4 border-b border-slate-100 pb-2">Active damage audit checks ledger</h3>
                        
                        <div class="overflow-x-auto text-xs">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Asset Plate</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Driver Name</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Before Scratches/Dents</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">After Scratches/Dents</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left font-bold text-slate-500 uppercase tracking-wider">Handover Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-150">
                                    @forelse($damageAudits as $audit)
                                        <tr class="hover:bg-slate-50/40">
                                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800">{{ $audit->vehicle->license_plate }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-bold">{{ $audit->driver->user->name ?? 'Unknown' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-semibold">S: {{ $audit->before_trip_scratches }} | D: {{ $audit->before_trip_dents }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-semibold">S: {{ $audit->after_trip_scratches }} | D: {{ $audit->after_trip_dents }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold
                                                    {{ $audit->status === 'review_pending' ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-green-100 text-green-700 border border-green-200' }}">
                                                    {{ strtoupper($audit->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-slate-500 font-semibold">{{ $audit->audit_date }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-6 py-4 text-center text-slate-400">No damage handover checks logged.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
             </div>

        </div>

        <!-- Create/Edit Modal Component -->
        <div x-data="{ open: false }" @open-create-modal.window="open = true" @close-create-modal.window="open = false" x-show="open" x-cloak class="fixed inset-0 bg-slate-900/60 backdrop-blur z-50 flex items-center justify-center p-4" x-transition>
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl p-8" @click.away="open = false">
                <div class="flex justify-between items-center border-b border-slate-100 pb-4 mb-6">
                    <h3 class="text-xl font-bold text-slate-800">{{ $isEditing ? 'Edit Fleet Asset' : 'Add Fleet Asset' }}</h3>
                    <button wire:click="resetInputFields" @click="open = false" class="text-slate-400 hover:text-slate-650">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="space-y-6 text-xs">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">License Plate *</label>
                            <input wire:model="license_plate" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold" required>
                            @error('license_plate') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Brand *</label>
                            <input wire:model="brand" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold" required>
                            @error('brand') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Model *</label>
                            <input wire:model="model" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold" required>
                            @error('model') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Asset Type *</label>
                            <input wire:model="type" type="text" placeholder="e.g. Heavy Truck, Excavator" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold" required>
                            @error('type') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Manufacture Year *</label>
                            <input wire:model="year" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold" required>
                            @error('year') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Fuel Type *</label>
                            <select wire:model="fuel_type" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                <option value="Diesel">Diesel</option>
                                <option value="Petrol">Petrol</option>
                                <option value="Electric">Electric</option>
                            </select>
                            @error('fuel_type') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Capacity (kg)</label>
                            <input wire:model="capacity" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                            @error('capacity') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Purchase Cost ($)</label>
                            <input wire:model="purchase_cost" type="number" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                            @error('purchase_cost') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Purchase Date</label>
                            <input wire:model="purchase_date" type="date" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                            @error('purchase_date') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Carbon Emissions (CO2 g/km)</label>
                            <input wire:model="carbon_emissions" type="number" step="0.1" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                            @error('carbon_emissions') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Lifecycle Stage *</label>
                            <select wire:model="lifecycle_stage" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                <option value="active">Active Operations</option>
                                <option value="purchase">Acquisition Stage</option>
                                <option value="registration">Registration Stage</option>
                                <option value="maintenance">Undergoing Maintenance</option>
                                <option value="depreciation">Depreciating Asset</option>
                                <option value="resale">For Resale</option>
                                <option value="disposed">Disposed / Scrapped</option>
                            </select>
                            @error('lifecycle_stage') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1 font-bold">Operational Status *</label>
                            <select wire:model="status" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg p-2 font-semibold">
                                <option value="available">Available</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="active">Active on job</option>
                            </select>
                            @error('status') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex gap-4 items-center justify-end border-t border-slate-100 pt-6">
                        <button type="button" wire:click="resetInputFields" @click="open = false" class="bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 px-6 py-2.5 rounded-xl font-bold transition">
                            Cancel
                        </button>
                        <button type="submit" @click="setTimeout(() => { open = false; }, 200)" class="bg-indigo-650 hover:bg-indigo-755 text-white px-6 py-2.5 rounded-xl font-bold shadow-sm transition">
                            {{ $isEditing ? 'Update Asset' : 'Save Asset' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Leaflet JS loaded at end -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('fleetMap', () => ({
            map: null,
            vehicles: @json($vehicles),
            init() {
                setTimeout(() => {
                    this.map = L.map('fleet-map').setView([25.2048, 55.2708], 9);
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(this.map);
                    
                    const vehicleIcon = L.divIcon({
                        html: `<div class='w-6 h-6 bg-indigo-600 rounded-full border-2 border-white shadow flex items-center justify-center text-white text-[10px] font-bold'>🚚</div>`,
                        className: '',
                        iconSize: [24, 24]
                    });

                    this.vehicles.forEach(v => {
                        if (v.latitude && v.longitude) {
                            L.marker([v.latitude, v.longitude], {icon: vehicleIcon})
                                .addTo(this.map)
                                .bindPopup(`<b>${v.brand || 'Vehicle'} ${v.model || ''}</b><br>Plate: ${v.license_plate}<br>Health: ${v.health_score}/100<br>Status: ${v.status}`);
                        }
                    });
                }, 200);
            }
        }));
    });
</script>

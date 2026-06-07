<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'license_plate', 'type', 'capacity', 'status', 'latitude', 'longitude',
        'brand', 'model', 'year', 'fuel_type', 'purchase_cost', 'purchase_date',
        'lifecycle_stage', 'health_score', 'risk_level', 'digital_twin_status',
        'carbon_emissions', 'qr_code_token', 'next_service_date'
    ];

    protected $casts = [
        'digital_twin_status' => 'json',
        'next_service_date' => 'datetime'
    ];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function predictiveMaintenances()
    {
        return $this->hasMany(PredictiveMaintenance::class);
    }

    public function fuelAnomalies()
    {
        return $this->hasMany(FuelAnomaly::class);
    }

    public function fleetExpenses()
    {
        return $this->hasMany(FleetExpense::class);
    }

    public function damageAudits()
    {
        return $this->hasMany(VehicleDamageAudit::class);
    }

    public function equipmentUsageCharges()
    {
        return $this->hasMany(EquipmentUsageCharge::class);
    }

    public function marketplaceTransfers()
    {
        return $this->hasMany(FleetMarketplaceTransfer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'status'];

    public function fleetExpenses()
    {
        return $this->hasMany(FleetExpense::class);
    }

    public function equipmentUsageCharges()
    {
        return $this->hasMany(EquipmentUsageCharge::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(FleetMarketplaceTransfer::class, 'from_project_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(FleetMarketplaceTransfer::class, 'to_project_id');
    }
}

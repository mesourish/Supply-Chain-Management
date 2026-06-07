<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentUsageCharge extends Model
{
    protected $table = 'equipment_usage_charges';

    protected $fillable = [
        'vehicle_id',
        'project_id',
        'usage_hours',
        'hourly_rate',
        'total_charge',
        'billing_date',
        'status',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

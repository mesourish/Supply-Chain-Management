<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'license_number', 'status', 'latitude', 'longitude', 'current_vehicle_id',
        'safety_score', 'fuel_efficiency_score', 'attendance_score', 'overall_rating',
        'rewards_count', 'penalties_count'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The vehicle currently assigned to this driver.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'current_vehicle_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function damageAudits()
    {
        return $this->hasMany(VehicleDamageAudit::class);
    }
}


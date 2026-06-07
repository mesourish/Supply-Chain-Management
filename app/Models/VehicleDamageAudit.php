<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleDamageAudit extends Model
{
    protected $table = 'vehicle_damage_audits';

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'audit_date',
        'before_trip_scratches',
        'after_trip_scratches',
        'before_trip_dents',
        'after_trip_dents',
        'before_trip_notes',
        'after_trip_notes',
        'status',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}

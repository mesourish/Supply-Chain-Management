<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelAnomaly extends Model
{
    protected $table = 'fuel_anomalies';

    protected $fillable = [
        'vehicle_id',
        'expected_fuel',
        'actual_fuel',
        'variance',
        'anomaly_date',
        'notes',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = ['license_plate', 'type', 'capacity', 'status', 'latitude', 'longitude'];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetExpense extends Model
{
    protected $table = 'fleet_expenses';

    protected $fillable = [
        'vehicle_id',
        'category',
        'amount',
        'date_incurred',
        'project_id',
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

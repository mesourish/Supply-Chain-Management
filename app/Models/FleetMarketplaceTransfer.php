<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetMarketplaceTransfer extends Model
{
    protected $table = 'fleet_marketplace_transfers';

    protected $fillable = [
        'vehicle_id',
        'from_project_id',
        'to_project_id',
        'request_date',
        'status',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fromProject()
    {
        return $this->belongsTo(Project::class, 'from_project_id');
    }

    public function toProject()
    {
        return $this->belongsTo(Project::class, 'to_project_id');
    }
}

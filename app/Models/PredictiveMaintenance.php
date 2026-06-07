<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PredictiveMaintenance extends Model
{
    protected $table = 'predictive_maintenances';

    protected $fillable = [
        'vehicle_id',
        'likely_issue',
        'prediction_days_range',
        'confidence_score',
        'status',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}

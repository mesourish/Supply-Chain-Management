<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkCenter extends Model
{
    protected $table = 'work_centers';

    protected $fillable = [
        'name',
        'capacity_limit',
        'hourly_labor_rate',
    ];
}

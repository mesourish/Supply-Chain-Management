<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMaterialRequest extends Model
{
    protected $fillable = [
        'project_id',
        'product_id',
        'warehouse_bin_id',
        'quantity_requested',
        'quantity_reserved',
        'status',
        'notes',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bin()
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }
}

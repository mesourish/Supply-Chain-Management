<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'location', 'code', 'capacity', 'manager', 'phone', 'email', 'notes', 'is_active',
        'contact_person_name', 'contact_number', 'contact_email', 'address',
    ];

    public function bins()
    {
        return $this->hasMany(WarehouseBin::class);
    }

    public function activeBins()
    {
        return $this->hasMany(WarehouseBin::class)->where('is_active', true);
    }

    /**
     * Total number of unique products stored across all bins
     */
    public function getTotalProductsAttribute()
    {
        return BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->id))
            ->where('quantity', '>', 0)
            ->distinct('product_id')
            ->count('product_id');
    }

    /**
     * Total units across all bins in this warehouse
     */
    public function getTotalStockUnitsAttribute()
    {
        return BinProductStock::whereHas('bin', fn($q) => $q->where('warehouse_id', $this->id))
            ->sum('quantity');
    }
}

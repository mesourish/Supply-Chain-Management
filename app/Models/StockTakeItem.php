<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTakeItem extends Model
{
    protected $fillable = [
        'stock_take_id', 'warehouse_bin_id', 'product_id',
        'system_quantity', 'counted_quantity', 'status', 'counted_by', 'notes'
    ];

    public function stockTake()
    {
        return $this->belongsTo(StockTake::class);
    }

    public function bin()
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function counter()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function getVarianceAttribute()
    {
        if ($this->counted_quantity === null) return null;
        return $this->counted_quantity - $this->system_quantity;
    }
}

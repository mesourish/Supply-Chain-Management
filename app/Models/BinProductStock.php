<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BinProductStock extends Model
{
    protected $table = 'bin_product_stock';

    protected $fillable = ['warehouse_bin_id', 'product_id', 'quantity', 'unit_cost'];

    public function bin()
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category',
        'brand',
        'description',
        'unit_of_measure',
        'weight',
        'cost_price',
        'unit_price',
        'reorder_level',
    ];

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
                    ->withPivot('price', 'supplier_sku')
                    ->withTimestamps();
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function binStocks()
    {
        return $this->hasMany(BinProductStock::class);
    }
}

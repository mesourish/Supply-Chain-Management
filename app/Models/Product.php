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
        'product_type',
        'route',
        'brand',
        'description',
        'unit_of_measure',
        'weight',
        'cost_price',
        'unit_price',
        'reorder_level',
        'min_stock',
        'max_stock',
        'image_path',
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

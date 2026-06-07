<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    protected $table = 'bom_items';

    protected $fillable = [
        'bom_id',
        'component_product_id',
        'quantity_required',
    ];

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}

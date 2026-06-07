<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillOfMaterial extends Model
{
    protected $table = 'bills_of_materials';

    protected $fillable = [
        'product_id',
        'bom_code',
        'name',
        'output_quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(BomItem::class, 'bom_id');
    }
}

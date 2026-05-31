<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'from_bin_id',
        'to_bin_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fromBin()
    {
        return $this->belongsTo(WarehouseBin::class, 'from_bin_id');
    }

    public function toBin()
    {
        return $this->belongsTo(WarehouseBin::class, 'to_bin_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function grn()
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'reference_id');
    }
}

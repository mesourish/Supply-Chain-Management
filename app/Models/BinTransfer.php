<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BinTransfer extends Model
{
    protected $fillable = [
        'reference_no', 'product_id', 'from_bin_id', 'to_bin_id',
        'quantity', 'reason', 'transferred_by'
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

    public function transferrer()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}

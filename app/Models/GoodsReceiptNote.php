<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptNote extends Model
{
    use SoftDeletes;

    protected $fillable = ['purchase_order_id', 'user_id', 'status', 'notes'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    protected $fillable = ['return_request_id', 'product_id', 'quantity', 'condition', 'resolution'];

    public function returnRequest() { return $this->belongsTo(ReturnRequest::class); }
    public function product() { return $this->belongsTo(Product::class); }
}

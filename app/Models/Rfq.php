<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_no',
        'supplier_id',
        'status',
        'total_amount',
        'delivery_date',
        'notes',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bids()
    {
        return $this->hasMany(RfqBid::class);
    }
}


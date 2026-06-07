<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id', 
        'status', 
        'subtotal',
        'gst_type',
        'gst_percentage',
        'gst_amount',
        'total_amount',
        'remarks',
        'terms_and_conditions',
        'contact_person_id',
        'billing_address_id',
        'shipping_address_id',
        'currency_code',
        'exchange_rate',
        'approval_status',
        'approval_notes',
        'expected_delivery',
    ];

    protected $casts = [
        'expected_delivery' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }



    public function contactPerson()
    {
        return $this->belongsTo(ContactPerson::class);
    }

    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function grns()
    {
        return $this->hasMany(GoodsReceiptNote::class);
    }

    public function logs()
    {
        return $this->hasMany(PurchaseOrderLog::class)->latest();
    }
}

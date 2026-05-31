<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 
        'status', 
        'total_amount',
        'tax_amount',
        'shipping_amount',
        'notes',
        'contact_person_id',
        'billing_address_id',
        'shipping_address_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
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
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}

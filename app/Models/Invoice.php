<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sales_order_id',
        'customer_id',
        'status',
        'amount',
        'tax_amount',
        'gst_type',
        'gst_percentage',
        'shipping_amount',
        'issue_date',
        'due_date',
        'description',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }


}

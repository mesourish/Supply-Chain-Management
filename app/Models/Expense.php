<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'category',
        'amount',
        'expense_date',
        'reference_number',
        'notes',
        'attachment_path',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}

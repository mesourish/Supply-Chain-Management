<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivable extends Model
{

    protected $fillable = ['invoice_id', 'customer_id', 'amount', 'status', 'attachment_path'];

    protected static function booted()
    {
        static::saved(function ($receivable) {
            if ($receivable->invoice_id) {
                $invoice = Invoice::find($receivable->invoice_id);
                if ($invoice) {
                    $newStatus = $receivable->status === 'paid' ? 'paid' : ($receivable->status === 'partial' ? 'partial' : 'unpaid');
                    if ($invoice->status !== $newStatus) {
                        $invoice->update(['status' => $newStatus]);
                    }
                }
            }
        });
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function getAmountPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    public function getBalanceAttribute()
    {
        return $this->amount - $this->amount_paid;
    }
}

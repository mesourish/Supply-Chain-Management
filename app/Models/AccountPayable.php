<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountPayable extends Model
{

    protected $fillable = ['purchase_order_id', 'supplier_id', 'amount', 'status', 'attachment_path'];

    protected static function booted()
    {
        static::saved(function ($accountPayable) {
            if ($accountPayable->wasRecentlyCreated) {
                try {
                    \App\Helpers\AccountingJournalHelper::postGoodsReceiptReceived($accountPayable);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("AccountPayable GL posting failed: " . $e->getMessage());
                }
            }
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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

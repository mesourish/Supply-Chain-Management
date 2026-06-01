<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $fillable = [
        'account_payable_id', 'account_receivable_id', 'amount', 'payment_date', 
        'reference_number', 'attachment_path', 'notes'
    ];

    protected static function booted()
    {
        static::saved(function ($paymentLog) {
            $paymentLog->syncParentBalances();
        });

        static::deleted(function ($paymentLog) {
            $paymentLog->syncParentBalances();
        });
    }

    public function syncParentBalances()
    {
        if ($this->account_receivable_id) {
            $receivable = AccountReceivable::find($this->account_receivable_id);
            if ($receivable) {
                $totalPaid = $receivable->payments()->sum('amount');
                $newStatus = 'unpaid';
                if ($totalPaid >= $receivable->amount) {
                    $newStatus = 'paid';
                } elseif ($totalPaid > 0) {
                    $newStatus = 'partial';
                }
                $receivable->update(['status' => $newStatus]);
            }
        }

        if ($this->account_payable_id) {
            $payable = AccountPayable::find($this->account_payable_id);
            if ($payable) {
                $totalPaid = $payable->payments()->sum('amount');
                $newStatus = 'unpaid';
                if ($totalPaid >= $payable->amount) {
                    $newStatus = 'paid';
                } elseif ($totalPaid > 0) {
                    $newStatus = 'partial';
                }
                $payable->update(['status' => $newStatus]);
            }
        }
    }

    public function accountPayable()
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function accountReceivable()
    {
        return $this->belongsTo(AccountReceivable::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $fillable = [
        'account_payable_id', 'account_receivable_id', 'amount', 'payment_date', 
        'reference_number', 'attachment_path', 'notes'
    ];

    public function accountPayable()
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function accountReceivable()
    {
        return $this->belongsTo(AccountReceivable::class);
    }
}

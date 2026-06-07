<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'accounts';

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'is_active',
    ];

    /**
     * Get all journal lines referencing this account.
     */
    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Get net account balance.
     * Assets and Expenses are debits positive (+debit -credit).
     * Liabilities, Equity, and Revenues are credits positive (+credit -debit).
     */
    public function getBalanceAttribute(): float
    {
        $debits = $this->journalLines()->sum('debit_amount');
        $credits = $this->journalLines()->sum('credit_amount');

        if (in_array($this->account_type, ['asset', 'expense'])) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}

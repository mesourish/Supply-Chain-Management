<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $table = 'journal_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit_amount',
        'credit_amount',
    ];

    /**
     * Get the journal entry header.
     */
    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the ledger account.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

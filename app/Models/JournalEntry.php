<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'entry_number',
        'reference_source',
        'posting_date',
        'description',
    ];

    protected $casts = [
        'posting_date' => 'date',
    ];

    /**
     * Get the lines for the journal entry.
     */
    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Helper to verify if the journal entry is balanced (debits == credits).
     */
    public function isBalanced(): bool
    {
        $debitTotal = $this->lines()->sum('debit_amount');
        $creditTotal = $this->lines()->sum('credit_amount');
        
        // Use a small epsilon to avoid float rounding issues
        return abs($debitTotal - $creditTotal) < 0.001;
    }
}

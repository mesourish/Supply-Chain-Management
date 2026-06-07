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
        'currency_code',
        'exchange_rate',
    ];

    protected static function booted()
    {
        static::saved(function ($invoice) {
            $receivable = AccountReceivable::where('invoice_id', $invoice->id)->first();
            if ($receivable) {
                $newStatus = $invoice->status === 'paid' ? 'paid' : ($invoice->status === 'unpaid' ? 'unpaid' : 'partial');
                if ($receivable->status !== $newStatus) {
                    $receivable->update(['status' => $newStatus]);
                }

                if ($invoice->status === 'paid') {
                    $totalPaid = $receivable->payments()->sum('amount');
                    $variance = $invoice->amount - $totalPaid;
                    if ($variance > 0) {
                        PaymentLog::create([
                            'account_receivable_id' => $receivable->id,
                            'amount' => $variance,
                            'payment_date' => now()->toDateString(),
                            'notes' => 'Auto-logged payment upon marking Invoice as Paid',
                        ]);
                    }
                }
            }

            if ($invoice->wasRecentlyCreated) {
                try {
                    \App\Helpers\AccountingJournalHelper::postInvoiceIssued($invoice);
                } catch (\Exception $e) {
                    // Fail-safe logging
                    \Illuminate\Support\Facades\Log::error("Invoice GL posting failed: " . $e->getMessage());
                }
            }
        });
    }

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

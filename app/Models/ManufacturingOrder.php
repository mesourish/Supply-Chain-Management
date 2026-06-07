<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManufacturingOrder extends Model
{
    protected $table = 'manufacturing_orders';

    protected static function booted()
    {
        static::saved(function ($mo) {
            if (($mo->wasChanged('status') || $mo->wasRecentlyCreated) && $mo->status === 'completed') {
                try {
                    // Create pending quality check upon completion
                    \App\Helpers\QualityControlHelper::createMoCheck($mo);

                    // Post journal entry to General Ledger
                    \App\Helpers\AccountingJournalHelper::postManufacturingCompleted($mo);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("ManufacturingOrder GL/QC posting failed: " . $e->getMessage());
                }
            }
        });
    }

    protected $fillable = [
        'mo_number',
        'product_id',
        'bom_id',
        'quantity_to_produce',
        'status',
        'sales_order_id',
        'scheduled_start_date',
        'actual_completed_date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityCheck extends Model
{
    protected $table = 'quality_checks';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'product_id',
        'status',
        'findings_notes',
        'inspector_user_id',
        'inspected_at',
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
    ];

    /**
     * Get the owning reference model (GoodsReceiptNote or ManufacturingOrder).
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Get the product associated with the quality check.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who inspected the goods.
     */
    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }
}

<?php

namespace App\Helpers;

use App\Models\QualityCheck;
use App\Models\GoodsReceiptNote;
use App\Models\ManufacturingOrder;

class QualityControlHelper
{
    /**
     * Create a pending quality check for a Goods Receipt Note item.
     */
    public static function createGrnCheck(GoodsReceiptNote $grn, int $productId): void
    {
        // Check if one already exists for this GRN and product to prevent duplicates
        $exists = QualityCheck::where('reference_type', GoodsReceiptNote::class)
            ->where('reference_id', $grn->id)
            ->where('product_id', $productId)
            ->exists();

        if (!$exists) {
            QualityCheck::create([
                'reference_type' => GoodsReceiptNote::class,
                'reference_id' => $grn->id,
                'product_id' => $productId,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Create a pending quality check for a Manufacturing Order.
     */
    public static function createMoCheck(ManufacturingOrder $mo): void
    {
        $exists = QualityCheck::where('reference_type', ManufacturingOrder::class)
            ->where('reference_id', $mo->id)
            ->where('product_id', $mo->product_id)
            ->exists();

        if (!$exists) {
            QualityCheck::create([
                'reference_type' => ManufacturingOrder::class,
                'reference_id' => $mo->id,
                'product_id' => $mo->product_id,
                'status' => 'pending',
            ]);
        }
    }
}

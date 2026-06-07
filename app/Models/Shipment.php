<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        static::saved(function ($shipment) {
            if (($shipment->wasChanged('status') || $shipment->wasRecentlyCreated) && $shipment->status === 'delivered') {
                try {
                    \App\Helpers\AccountingJournalHelper::postShipmentDelivered($shipment);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Shipment GL posting failed: " . $e->getMessage());
                }
            }
        });
    }

    protected $fillable = [
        'sales_order_id', 'vehicle_id', 'driver_id', 'status', 'tracking_number',
        'origin_address', 'destination_address', 'origin_lat', 'origin_lng', 'dest_lat', 'dest_lng'
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }



    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseBin extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id', 'bin_code', 'zone', 'aisle', 'rack', 'shelf',
        'bin_type', 'max_weight_kg', 'max_volume_m3', 'is_active', 'notes',
        'bin_status', 'pick_face_flag', 'bin_sequence_no', 'staging_zone_flag',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'pick_face_flag'  => 'boolean',
        'staging_zone_flag' => 'boolean',
    ];

    protected $appends = ['barcode', 'full_label'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'to_bin_id');
    }

    public function stockEntries()
    {
        return $this->hasMany(BinProductStock::class, 'warehouse_bin_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'bin_product_stock', 'warehouse_bin_id', 'product_id')
                    ->withPivot('quantity', 'unit_cost')
                    ->withTimestamps();
    }

    /**
     * Current total quantity of all products in this bin
     */
    public function currentStock()
    {
        return $this->stockEntries()->sum('quantity');
    }

    /**
     * Generate a virtual barcode attribute for picking
     */
    public function getBarcodeAttribute()
    {
        return 'BIN-' . str_pad($this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Human-readable label like "Zone A | Aisle 01 | Rack R1 | Shelf S2 (BIN-00001)"
     */
    public function getFullLabelAttribute()
    {
        $parts = array_filter([
            $this->zone  ? "Zone {$this->zone}" : null,
            $this->aisle ? "Aisle {$this->aisle}" : null,
            $this->rack  ? "Rack {$this->rack}" : null,
            $this->shelf ? "Shelf {$this->shelf}" : null,
        ]);
        return (count($parts) > 0 ? implode(' › ', $parts) . ' · ' : '') . $this->bin_code;
    }
}

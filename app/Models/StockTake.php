<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTake extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_no', 'warehouse_id', 'created_by', 'approved_by',
        'status', 'scope', 'scope_filter', 'notes', 'counted_at', 'approved_at'
    ];

    protected $casts = [
        'counted_at'  => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockTakeItem::class);
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'draft'            => ['label' => 'Draft', 'color' => 'gray'],
            'in_progress'      => ['label' => 'In Progress', 'color' => 'blue'],
            'pending_approval' => ['label' => 'Pending Approval', 'color' => 'yellow'],
            'approved'         => ['label' => 'Approved', 'color' => 'green'],
            'cancelled'        => ['label' => 'Cancelled', 'color' => 'red'],
            default            => ['label' => 'Unknown', 'color' => 'gray'],
        };
    }

    public function getTotalVarianceAttribute()
    {
        return $this->items()->whereNotNull('counted_quantity')->sum(
            \DB::raw('counted_quantity - system_quantity')
        );
    }

    public function getCountedItemsCountAttribute()
    {
        return $this->items()->whereNotNull('counted_quantity')->count();
    }
}

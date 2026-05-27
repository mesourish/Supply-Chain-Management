<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = ['sales_order_id', 'project_id', 'status', 'amount'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

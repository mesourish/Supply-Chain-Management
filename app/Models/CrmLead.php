<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'title',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'deal_value',
        'pipeline_stage',
        'deal_probability',
        'source',
        'notes',
        'assigned_user_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmActivity extends Model
{
    protected $fillable = [
        'crm_lead_id',
        'type',
        'description',
        'activity_date',
        'user_id',
    ];

    public function lead()
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

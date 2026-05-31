<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPerson extends Model
{
    protected $table = 'contact_persons';

    protected $fillable = [
        'contactable_id',
        'contactable_type',
        'name',
        'email',
        'phone',
        'designation',
        'is_primary'
    ];

    public function contactable()
    {
        return $this->morphTo();
    }
}

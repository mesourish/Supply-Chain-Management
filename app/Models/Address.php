<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 'addresses';

    protected $fillable = [
        'addressable_id',
        'addressable_type',
        'type',
        'title',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default_billing',
        'is_default_shipping'
    ];

    public function addressable()
    {
        return $this->morphTo();
    }
}

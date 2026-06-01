<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        static::saved(function ($customer) {
            if ($customer->contact_person) {
                $customer->contactPersons()->updateOrCreate(
                    ['is_primary' => true],
                    [
                        'name' => $customer->contact_person,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'designation' => 'Primary Contact',
                    ]
                );
            }

            if ($customer->billing_address) {
                $customer->addresses()->updateOrCreate(
                    ['type' => 'billing'],
                    [
                        'address_line_1' => $customer->billing_address,
                        'is_default_billing' => true,
                        'is_default_shipping' => false,
                    ]
                );
            }

            if ($customer->shipping_address) {
                $customer->addresses()->updateOrCreate(
                    ['type' => 'shipping'],
                    [
                        'address_line_1' => $customer->shipping_address,
                        'is_default_billing' => false,
                        'is_default_shipping' => true,
                    ]
                );
            }
        });
    }

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'tax_id',
        'billing_address',
        'shipping_address',
    ];

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, SalesOrder::class);
    }

    public function accountReceivables()
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function crmLeads()
    {
        return $this->hasMany(CrmLead::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }



    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function contactPersons()
    {
        return $this->morphMany(ContactPerson::class, 'contactable');
    }
}

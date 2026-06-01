<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        static::saved(function ($supplier) {
            if ($supplier->contact_person) {
                $supplier->contactPersons()->updateOrCreate(
                    ['is_primary' => true],
                    [
                        'name' => $supplier->contact_person,
                        'email' => $supplier->email,
                        'phone' => $supplier->phone,
                        'designation' => 'Primary Contact',
                    ]
                );
            }

            if ($supplier->address) {
                $supplier->addresses()->updateOrCreate(
                    ['type' => 'billing'],
                    [
                        'address_line_1' => $supplier->address,
                        'is_default_billing' => true,
                        'is_default_shipping' => false,
                    ]
                );
                $supplier->addresses()->updateOrCreate(
                    ['type' => 'shipping'],
                    [
                        'address_line_1' => $supplier->address,
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
        'address',
        'is_active',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function contactPersons()
    {
        return $this->morphMany(ContactPerson::class, 'contactable');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
                    ->withPivot('price', 'supplier_sku')
                    ->withTimestamps();
    }

    public function accountPayables()
    {
        return $this->hasMany(AccountPayable::class);
    }
}

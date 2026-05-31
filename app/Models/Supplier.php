<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

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

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConstant extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'value',
        'is_active',
    ];
}

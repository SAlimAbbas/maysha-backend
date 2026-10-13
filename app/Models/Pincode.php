<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pincode extends Model
{
    use HasFactory;

    protected $fillable = [
        'pincode',
        'city',
        'state',
        'serviceable',
        'cod_available',
        'eta_days',
    ];

    protected $casts = [
        'serviceable' => 'boolean',
        'cod_available' => 'boolean',
        'eta_days' => 'integer',
    ];
}

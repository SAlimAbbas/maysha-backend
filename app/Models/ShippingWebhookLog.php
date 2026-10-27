<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_type',
        'body_hash',
        'headers',
        'payload',
        'ip_address',
        'processed',
        'error_message',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'processed' => 'boolean',
    ];
}

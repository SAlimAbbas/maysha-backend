<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'sr_order_id',
        'sr_shipment_id',
        'awb_code',
        'courier_id',
        'courier_name',
        'status',
        'sr_status',
        'sr_status_code',
        'label_url',
        'manifest_url',
        'invoice_url',
        'tracking_url',
        'pickup_scheduled_at',
        'pickup_token',
        'shipping_charge_paise',
        'cod_charge_paise',
        'weight_grams',
        'length_cm',
        'breadth_cm',
        'height_cm',
        'cancelled_at',
        'is_return',
        'parent_shipment_id',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'sr_order_id' => 'integer',
        'sr_shipment_id' => 'integer',
        'courier_id' => 'integer',
        'sr_status_code' => 'integer',
        'shipping_charge_paise' => 'integer',
        'cod_charge_paise' => 'integer',
        'weight_grams' => 'integer',
        'length_cm' => 'decimal:2',
        'breadth_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'is_return' => 'boolean',
        'pickup_scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at', 'asc')->orderBy('id', 'asc');
    }

    public function parentShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'parent_shipment_id');
    }

    public function returnShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'parent_shipment_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled', 'rto_delivered', 'refunded'], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelled_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled', 'rto_delivered', 'refunded']);
    }

    public function scopeTerminal($query)
    {
        return $query->whereIn('status', ['delivered', 'cancelled', 'rto_delivered', 'refunded']);
    }
}

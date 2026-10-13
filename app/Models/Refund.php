<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'order_id',
        'amount_paise',
        'reason',
        'status',
        'gateway_refund_id',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getAmountRupeesAttribute(): int
    {
        return (int) round($this->amount_paise / 100);
    }
}

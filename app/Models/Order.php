<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'idempotency_key',
        'invoice_no',
        'user_id',
        'guest_email',
        'guest_phone',
        'status',
        'subtotal_paise',
        'discount_paise',
        'shipping_paise',
        'tax_paise',
        'total_paise',
        'currency',
        'coupon_code',
        'shipping_address',
        'payment_method',
        'notes',
        'reserved_until',
        'tracking_no',
        'courier',
        'placed_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
    ];

    public const ALLOWED_TRANSITIONS = [
        'pending_payment' => ['processing', 'paid', 'cancelled'],
        'paid' => ['processing', 'shipped', 'cancelled', 'refunded'],
        'processing' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['delivered', 'ndr', 'rto', 'exception', 'refunded'],
        'out_for_delivery' => ['delivered', 'ndr', 'rto', 'exception'],
        'ndr' => ['shipped', 'delivered', 'rto', 'exception'],
        'delivered' => ['refunded', 'return_requested', 'returned'],
        'rto' => ['rto_delivered', 'refunded', 'cancelled'],
        'rto_delivered' => ['refunded', 'cancelled'],
        'cancelled' => ['refunded'],
        'refunded' => [],
        'returned' => ['refunded'],
        'exception' => ['shipped', 'delivered', 'rto', 'cancelled', 'refunded'],
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        if ($this->status === $newStatus) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowed, true);
    }

    protected $casts = [
        'subtotal_paise' => 'integer',
        'discount_paise' => 'integer',
        'shipping_paise' => 'integer',
        'tax_paise' => 'integer',
        'total_paise' => 'integer',
        'shipping_address' => 'array',
        'reserved_until' => 'datetime',
        'placed_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function getSubtotalRupeesAttribute(): int
    {
        return (int) round($this->subtotal_paise / 100);
    }

    public function getDiscountRupeesAttribute(): int
    {
        return (int) round($this->discount_paise / 100);
    }

    public function getShippingRupeesAttribute(): int
    {
        return (int) round($this->shipping_paise / 100);
    }

    public function getTotalRupeesAttribute(): int
    {
        return (int) round($this->total_paise / 100);
    }

    public function getTaxRupeesAttribute(): int
    {
        return (int) round(($this->tax_paise ?? 0) / 100);
    }

    /**
     * Generate unique reference: MAY-XXXXXX
     */
    public static function generateReference(): string
    {
        do {
            $ref = 'MAY-'.strtoupper(Str::random(6));
        } while (self::where('reference', $ref)->exists());

        return $ref;
    }

    /**
     * Generate unique fiscal-year invoice number: INV/YY-YY/XXXXX
     */
    public static function generateInvoiceNo(): string
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('y');
        if ($currentMonth >= 4) {
            $fy = sprintf('%02d-%02d', $currentYear, $currentYear + 1);
        } else {
            $fy = sprintf('%02d-%02d', $currentYear - 1, $currentYear);
        }

        $count = self::whereNotNull('invoice_no')->where('invoice_no', 'like', "INV/{$fy}/%")->count() + 1;
        do {
            $invoiceNo = sprintf('INV/%s/%05d', $fy, $count);
            $count++;
        } while (self::where('invoice_no', $invoiceNo)->exists());

        return $invoiceNo;
    }
}

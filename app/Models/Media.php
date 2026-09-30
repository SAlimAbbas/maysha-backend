<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'provider',
        'provider_id',
        'type',
        'alt',
        'width',
        'height',
        'status',
        'uploaded_by',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Build the delivery URL based on provider and variant.
     */
    public function getDeliveryUrl(string $variant = 'card'): string
    {
        if ($this->provider === 'cloudflare_images') {
            $base = rtrim(env('CLOUDFLARE_IMAGES_DELIVERY_URL', 'https://imagedelivery.net/'), '/');

            return "{$base}/{$this->provider_id}/{$variant}";
        }

        if ($this->provider === 'cloudflare_stream') {
            $customerCode = env('CLOUDFLARE_STREAM_CUSTOMER_CODE', 'customer-code');

            return "https://{$customerCode}.cloudflarestream.com/{$this->provider_id}/manifest/video.m3u8";
        }

        return $this->provider_id;
    }
}

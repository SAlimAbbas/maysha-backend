<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'desktop_media_id',
        'mobile_media_id',
        'desktop_image',
        'mobile_image',
        'link_url',
        'badge_text',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'desktop_media_id' => 'integer',
        'mobile_media_id' => 'integer',
    ];

    public function desktopMedia()
    {
        return $this->belongsTo(Media::class, 'desktop_media_id');
    }

    public function mobileMedia()
    {
        return $this->belongsTo(Media::class, 'mobile_media_id');
    }

    public function getResolvedDesktopImageAttribute(): string
    {
        if ($this->desktopMedia) {
            return $this->desktopMedia->getDeliveryUrl('hero-desktop');
        }

        return $this->desktop_image ?: '';
    }

    public function getResolvedMobileImageAttribute(): string
    {
        if ($this->mobileMedia) {
            return $this->mobileMedia->getDeliveryUrl('hero-mobile');
        }

        return $this->mobile_image ?: $this->resolved_desktop_image;
    }
}

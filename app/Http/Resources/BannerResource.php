<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    /**
     * Transform the banner model into standard frontend contract shape.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'desktopImage' => $this->resolved_desktop_image,
            'mobileImage' => $this->resolved_mobile_image,
            'linkUrl' => $this->link_url,
            'badgeText' => $this->badge_text,
            'isActive' => (bool) $this->is_active,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}

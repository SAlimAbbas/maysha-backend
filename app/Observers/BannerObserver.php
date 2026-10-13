<?php

namespace App\Observers;

use App\Jobs\RevalidateStorefrontJob;
use App\Models\Banner;
use App\Services\CatalogCacheService;

class BannerObserver
{
    public function saved(Banner $banner): void
    {
        CatalogCacheService::invalidateBanners();
        RevalidateStorefrontJob::dispatch(['banners', 'home']);
    }

    public function deleted(Banner $banner): void
    {
        CatalogCacheService::invalidateBanners();
        RevalidateStorefrontJob::dispatch(['banners', 'home']);
    }
}

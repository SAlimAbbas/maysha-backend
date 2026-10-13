<?php

namespace App\Observers;

use App\Jobs\RevalidateStorefrontJob;
use App\Models\SiteSetting;
use App\Services\CatalogCacheService;

class SiteSettingObserver
{
    public function saved(SiteSetting $setting): void
    {
        CatalogCacheService::invalidateSettings();
        RevalidateStorefrontJob::dispatch(['settings', 'store-info', 'home']);
    }

    public function deleted(SiteSetting $setting): void
    {
        CatalogCacheService::invalidateSettings();
        RevalidateStorefrontJob::dispatch(['settings', 'store-info', 'home']);
    }
}

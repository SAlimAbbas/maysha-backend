<?php

namespace App\Observers;

use App\Jobs\RevalidateStorefrontJob;
use App\Models\Concern;
use App\Services\CatalogCacheService;

class ConcernObserver
{
    public function saved(Concern $concern): void
    {
        CatalogCacheService::invalidateConcerns();
        RevalidateStorefrontJob::dispatch(['concerns', 'catalog', "concern:{$concern->slug}"]);
    }

    public function deleted(Concern $concern): void
    {
        CatalogCacheService::invalidateConcerns();
        RevalidateStorefrontJob::dispatch(['concerns', 'catalog']);
    }
}

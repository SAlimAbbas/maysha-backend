<?php

namespace App\Observers;

use App\Jobs\RevalidateStorefrontJob;
use App\Models\Category;
use App\Services\CatalogCacheService;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        CatalogCacheService::invalidateCategories();
        RevalidateStorefrontJob::dispatch(['categories', 'catalog', "category:{$category->slug}"]);
    }

    public function deleted(Category $category): void
    {
        CatalogCacheService::invalidateCategories();
        RevalidateStorefrontJob::dispatch(['categories', 'catalog']);
    }
}

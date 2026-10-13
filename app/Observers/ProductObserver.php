<?php

namespace App\Observers;

use App\Jobs\RevalidateStorefrontJob;
use App\Models\Product;
use App\Services\CatalogCacheService;

class ProductObserver
{
    public function saved(Product $product): void
    {
        CatalogCacheService::invalidateProducts();
        RevalidateStorefrontJob::dispatch(['products', 'catalog', "product:{$product->slug}"]);
    }

    public function deleted(Product $product): void
    {
        CatalogCacheService::invalidateProducts();
        RevalidateStorefrontJob::dispatch(['products', 'catalog']);
    }

    public function restored(Product $product): void
    {
        CatalogCacheService::invalidateProducts();
        RevalidateStorefrontJob::dispatch(['products', 'catalog']);
    }
}

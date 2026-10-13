<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CatalogCacheService
{
    /**
     * Default TTL for catalog cache in seconds (1 hour).
     */
    public const DEFAULT_TTL = 3600;

    /**
     * Remember value with tags, fallback to key-prefix if tags are unsupported,
     * and gracefully degrade to executing $callback directly if cache store fails.
     */
    public static function remember(string $key, array $tags, int $ttlSeconds, Closure $callback): mixed
    {
        try {
            $supportsTags = method_exists(Cache::getStore(), 'tags');

            if ($supportsTags) {
                return Cache::tags($tags)->remember($key, $ttlSeconds, $callback);
            }

            // Fallback for file/database/array drivers that lack tagging
            return Cache::remember('tag:'.implode('_', $tags).":{$key}", $ttlSeconds, $callback);
        } catch (\Throwable $e) {
            // Graceful degradation: never crash if cache/Redis is unavailable
            Log::warning("Cache failed for key '{$key}': {$e->getMessage()}. Falling back to database.");

            return $callback();
        }
    }

    /**
     * Bust/invalidate cache tags safely.
     */
    public static function invalidateTags(array|string $tags): void
    {
        $tags = (array) $tags;

        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags($tags)->flush();
            } else {
                // For non-tagging stores, increment a tag version or flush specific pattern
                foreach ($tags as $tag) {
                    Cache::forget("tag:{$tag}");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to flush cache tags: {$e->getMessage()}");
        }
    }

    /**
     * Invalidate catalog caches on model mutations.
     */
    public static function invalidateProducts(): void
    {
        static::invalidateTags(['products', 'catalog']);
    }

    public static function invalidateCategories(): void
    {
        static::invalidateTags(['categories', 'catalog']);
    }

    public static function invalidateConcerns(): void
    {
        static::invalidateTags(['concerns', 'catalog']);
    }

    public static function invalidateBanners(): void
    {
        static::invalidateTags(['banners', 'home']);
    }

    public static function invalidateSettings(): void
    {
        static::invalidateTags(['settings', 'home']);
    }
}

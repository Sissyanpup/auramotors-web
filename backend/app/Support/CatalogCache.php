<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Driver-agnostic cache invalidation for the public vehicle catalog
 * (Cache::tags() isn't supported by the database cache store this project
 * uses, so a version counter is used instead: every cached catalog key
 * embeds the current version, and writes bump it so old keys are simply
 * never read again instead of needing an explicit flush).
 */
class CatalogCache
{
    public const TTL_SECONDS = 30;

    private const VERSION_KEY = 'vehicles_catalog_version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bump(): void
    {
        Cache::add(self::VERSION_KEY, 1, now()->addYear());
        Cache::increment(self::VERSION_KEY);
    }
}

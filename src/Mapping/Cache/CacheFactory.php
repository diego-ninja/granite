<?php

namespace Ninja\Granite\Mapping\Cache;

use Ninja\Granite\Mapping\Contracts\MappingCache;

/**
 * Factory for creating mapping caches.
 */
class CacheFactory
{
    /**
     * Cache directory for persistent caches.
     */
    private static string $cacheDir = '';

    /**
     * Create a mapping cache based on environment.
     *
     * @param string $type Cache type ('memory', 'shared', 'persistent')
     * @return MappingCache Mapping cache
     */
    public static function create(string $type = 'memory'): MappingCache
    {
        return match ($type) {
            'shared' => SharedMappingCache::getInstance(),
            'persistent' => new PersistentMappingCache(self::getCachePath()),
            default => new InMemoryMappingCache()
        };
    }

    /**
     * Set cache directory.
     *
     * @param string $dir Cache directory
     * @return void
     */
    public static function setCacheDirectory(string $dir): void
    {
        self::$cacheDir = rtrim($dir, '/\\');
    }

    /**
     * Get cache file path.
     *
     * @return string Cache file path
     */
    private static function getCachePath(): string
    {
        $dir = self::$cacheDir ?: sys_get_temp_dir();
        return $dir . '/granite_mapper_cache.php';
    }
}
<?php
// ABOUTME: Defines CacheFactory as part of the object mapping pipeline.
// ABOUTME: Owns the CacheFactory boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Cache;

use Ninja\Granite\Enums\CacheType;
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
     * @param CacheType $type Cache type ('memory', 'shared', 'persistent')
     * @return MappingCache Mapping cache
     */
    public static function create(CacheType $type = CacheType::Memory): MappingCache
    {
        return match ($type) {
            CacheType::Shared => SharedMappingCache::getInstance(),
            CacheType::Persistent => new PersistentMappingCache(self::getCachePath()),
            default => new InMemoryMappingCache(),
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
        $projectRoot = realpath(dirname(__DIR__, 3)) ?: dirname(__DIR__, 3);
        $projectNamespace = substr(hash('sha256', $projectRoot), 0, 20);
        $userNamespace = function_exists('posix_geteuid') ? (string) posix_geteuid() : get_current_user();

        return $dir . '/granite-mapper/' . $userNamespace . '-' . $projectNamespace . '/mapping-cache-v2.json';
    }
}

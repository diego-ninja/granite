<?php

namespace Ninja\Granite\Mapping\Cache;

use Ninja\Granite\Mapping\Contracts\MappingCache;

/**
 * Shared in-memory mapping cache (singleton).
 */
class SharedMappingCache implements MappingCache
{
    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Cache storage.
     */
    private InMemoryMappingCache $cache;

    /**
     * Hit/miss metrics.
     */
    private int $hits = 0;
    private int $misses = 0;

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct()
    {
        $this->cache = new InMemoryMappingCache();
    }

    /**
     * Get singleton instance.
     *
     * @return self Instance
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if a mapping configuration exists in cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return bool Whether the mapping exists in cache
     */
    public function has(string $sourceType, string $destinationType): bool
    {
        return $this->cache->has($sourceType, $destinationType);
    }

    /**
     * Get a mapping configuration from cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array|null Mapping configuration or null if not found
     */
    public function get(string $sourceType, string $destinationType): ?array
    {
        $result = $this->cache->get($sourceType, $destinationType);

        if (null !== $result) {
            $this->hits++;
        } else {
            $this->misses++;
        }

        return $result;
    }

    /**
     * Store a mapping configuration in cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param array $config Mapping configuration
     * @return void
     */
    public function put(string $sourceType, string $destinationType, array $config): void
    {
        $this->cache->put($sourceType, $destinationType, $config);
    }

    /**
     * Clear all cached mapping configurations.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache->clear();
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics
     */
    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'total' => $total,
            'hit_rate' => round($hitRate, 2) . '%',
        ];
    }
}

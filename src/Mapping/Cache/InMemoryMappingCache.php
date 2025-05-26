<?php

namespace Ninja\Granite\Mapping\Cache;

use Ninja\Granite\Mapping\Contracts\MappingCache;

/**
 * In-memory implementation of mapping cache.
 */
class InMemoryMappingCache implements MappingCache
{
    /**
     * Cached mapping configurations.
     *
     * @var array<string, array>
     */
    private array $cache = [];

    /**
     * Check if a mapping configuration exists in cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return bool Whether the mapping exists in cache
     */
    public function has(string $sourceType, string $destinationType): bool
    {
        $key = $this->getCacheKey($sourceType, $destinationType);
        return isset($this->cache[$key]);
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
        $key = $this->getCacheKey($sourceType, $destinationType);
        return $this->cache[$key] ?? null;
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
        $key = $this->getCacheKey($sourceType, $destinationType);
        $this->cache[$key] = $config;
    }

    /**
     * Clear all cached mapping configurations.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Get cache key for a type pair.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return string Cache key
     */
    private function getCacheKey(string $sourceType, string $destinationType): string
    {
        return $sourceType . '->' . $destinationType;
    }
}
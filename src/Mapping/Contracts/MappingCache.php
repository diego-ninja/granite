<?php

namespace Ninja\Granite\Mapping\Contracts;

/**
 * Interface for mapping configuration cache.
 */
interface MappingCache
{
    /**
     * Check if a mapping configuration exists in cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return bool Whether the mapping exists in cache
     */
    public function has(string $sourceType, string $destinationType): bool;

    /**
     * Get a mapping configuration from cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array|null Mapping configuration or null if not found
     */
    public function get(string $sourceType, string $destinationType): ?array;

    /**
     * Store a mapping configuration in cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param array $config Mapping configuration
     * @return void
     */
    public function put(string $sourceType, string $destinationType, array $config): void;

    /**
     * Clear all cached mapping configurations.
     *
     * @return void
     */
    public function clear(): void;
}
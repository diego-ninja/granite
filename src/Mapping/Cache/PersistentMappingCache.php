<?php

namespace Ninja\Granite\Mapping\Cache;

use Ninja\Granite\Mapping\Contracts\MappingCache;
use Throwable;

/**
 * File-based persistent mapping cache.
 */
class PersistentMappingCache implements MappingCache
{
    /**
     * In-memory cache for current request.
     */
    private InMemoryMappingCache $memoryCache;

    /**
     * Path to cache file.
     */
    private string $cachePath;

    /**
     * Whether the cache is dirty and needs saving.
     */
    private bool $isDirty = false;

    private bool $shutdownRegistered = false;

    /**
     * Constructor.
     *
     * @param string $cachePath Path to cache file
     */
    public function __construct(string $cachePath)
    {
        $this->memoryCache = new InMemoryMappingCache();
        $this->cachePath = $cachePath;
        $this->loadCache();

        $this->registerShutdownCallback();
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
        return $this->memoryCache->has($sourceType, $destinationType);
    }

    /**
     * Get a mapping configuration from cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array<string, array<string, mixed>>|null Mapping configuration or null if not found
     */
    public function get(string $sourceType, string $destinationType): ?array
    {
        return $this->memoryCache->get($sourceType, $destinationType);
    }

    /**
     * Store a mapping configuration in cache.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param array<string, array<string, mixed>> $config Mapping configuration
     * @return void
     */
    public function put(string $sourceType, string $destinationType, array $config): void
    {
        $this->memoryCache->put($sourceType, $destinationType, $config);
        $this->isDirty = true;
    }

    /**
     * Clear all cached mapping configurations.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->memoryCache->clear();
        $this->isDirty = true;
        $this->save(); // Save immediately when clearing
    }

    /**
     * Save cache to file.
     *
     * @return bool Whether the save was successful
     */
    public function save(): bool
    {
        $tmpFile = null;

        try {
            $cacheDir = dirname($this->cachePath);
            if ( ! is_dir($cacheDir)) {
                if ( ! @mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
                    return false;
                }
            }

            $payload = [
                'version' => 1,
                'mappings' => $this->extractCacheData(),
            ];
            $contents = json_encode($payload, JSON_THROW_ON_ERROR);
            $tmpFile = @tempnam($cacheDir, basename($this->cachePath) . '.tmp-');
            if (false === $tmpFile) {
                return false;
            }

            if (false === @file_put_contents($tmpFile, $contents, LOCK_EX)) {
                return false;
            }

            if ( ! @rename($tmpFile, $this->cachePath)) {
                return false;
            }

            $tmpFile = null;
            $this->isDirty = false;
            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if (is_string($tmpFile) && is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Save cache if dirty when shutting down.
     *
     * @return void
     */
    public function saveIfDirty(): void
    {
        if ($this->isDirty) {
            $this->save();
        }
    }

    /**
     * Load cache from file.
     *
     * @return void
     */
    private function loadCache(): void
    {
        if ( ! is_file($this->cachePath)) {
            return;
        }

        try {
            $cacheData = @file_get_contents($this->cachePath);
            if (false === $cacheData) {
                $this->isDirty = true;
                return;
            }

            $payload = json_decode($cacheData, true, 512, JSON_THROW_ON_ERROR);
            $mappings = $this->validatePayload($payload);
            if (null === $mappings) {
                $this->memoryCache->clear();
                $this->isDirty = true;
                return;
            }

            foreach ($mappings as $key => $config) {
                [$sourceType, $destinationType] = explode('->', $key, 2);
                $this->memoryCache->put($sourceType, $destinationType, $config);
            }
        } catch (Throwable) {
            $this->memoryCache->clear();
            $this->isDirty = true;
        }
    }

    /**
     * Extract cache data from memory cache.
     *
     * @return array<string, array<string, array<string, mixed>>> Cache data
     */
    private function extractCacheData(): array
    {
        $cacheData = [];

        foreach ($this->memoryCache->all() as $key => $config) {
            if ($this->isPersistableValue($config)) {
                $cacheData[$key] = $config;
            }
        }

        return $cacheData;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    private function validatePayload(mixed $payload): ?array
    {
        if ( ! is_array($payload) || 1 !== ($payload['version'] ?? null) || ! is_array($payload['mappings'] ?? null)) {
            return null;
        }

        $mappings = [];
        foreach ($payload['mappings'] as $key => $config) {
            if ( ! is_string($key) || 2 !== count(explode('->', $key, 2)) || ! is_array($config) || ! $this->isPersistableValue($config)) {
                continue;
            }

            [$sourceType, $destinationType] = explode('->', $key, 2);
            if ('' === $sourceType || '' === $destinationType || str_contains($destinationType, '->')) {
                continue;
            }

            $validatedConfig = [];
            foreach ($config as $property => $propertyConfig) {
                if ( ! is_string($property) || ! is_array($propertyConfig) || ! $this->isPersistableValue($propertyConfig)) {
                    $validatedConfig = null;
                    break;
                }

                $validatedPropertyConfig = [];
                foreach ($propertyConfig as $configKey => $configValue) {
                    if ( ! is_string($configKey)) {
                        $validatedConfig = null;
                        break 2;
                    }
                    $validatedPropertyConfig[$configKey] = $configValue;
                }
                $validatedConfig[$property] = $validatedPropertyConfig;
            }

            if (null !== $validatedConfig) {
                $mappings[$key] = $validatedConfig;
            }
        }

        return $mappings;
    }

    private function isPersistableValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                if ( ! $this->isPersistableValue($nestedValue)) {
                    return false;
                }
            }

            return true;
        }

        return null === $value || is_bool($value) || is_int($value) || is_float($value) || is_string($value);
    }

    private function registerShutdownCallback(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function([$this, 'saveIfDirty']);
        $this->shutdownRegistered = true;
    }
}

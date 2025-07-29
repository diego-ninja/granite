<?php

namespace Ninja\Granite\Mapping\Cache;

use Exception;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use ReflectionClass;

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

        // Register shutdown function to save cache automatically
        register_shutdown_function([$this, 'saveIfDirty']);
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
     * @return array|null Mapping configuration or null if not found
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
     * @param array $config Mapping configuration
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
        try {
            $cacheDir = dirname($this->cachePath);
            if ( ! is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $tmpFile = $this->cachePath . '.tmp';
            $result = file_put_contents($tmpFile, serialize($this->extractCacheData()), LOCK_EX);

            if (false !== $result) {
                rename($tmpFile, $this->cachePath);
                $this->isDirty = false;
                return true;
            }
        } catch (Exception $e) {
            // Ignore errors, just return false
        }

        return false;
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
        if ( ! file_exists($this->cachePath)) {
            return;
        }

        try {
            $cacheData = file_get_contents($this->cachePath);
            if (false === $cacheData) {
                return;
            }

            $data = unserialize($cacheData);
            if ( ! is_array($data)) {
                return;
            }

            foreach ($data as $key => $config) {
                if (is_string($key) && is_array($config)) {
                    $parts = explode('->', $key, 2);
                    if (2 === count($parts)) {
                        [$sourceType, $destinationType] = $parts;
                        $this->memoryCache->put($sourceType, $destinationType, $config);
                    }
                }
            }
        } catch (Exception $e) {
            // If loading fails, just start with an empty cache
            $this->memoryCache->clear();
        }
    }

    /**
     * Extract cache data from memory cache.
     *
     * @return array Cache data
     */
    private function extractCacheData(): array
    {
        $reflection = new ReflectionClass($this->memoryCache);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);

        $cacheData = $cacheProperty->getValue($this->memoryCache);

        return is_array($cacheData) ? $cacheData : [];
    }
}

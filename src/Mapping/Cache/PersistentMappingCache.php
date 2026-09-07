<?php
// ABOUTME: Defines PersistentMappingCache as part of the object mapping pipeline.
// ABOUTME: Owns the PersistentMappingCache boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Cache;

use Ninja\Granite\Mapping\Contracts\MappingCache;
use Throwable;

/**
 * File-based persistent mapping cache.
 */
class PersistentMappingCache implements MappingCache
{
    private const int SCHEMA_VERSION = 2;

    private const float FILESYSTEM_PROBE_INTERVAL_NANOSECONDS = 100_000_000.0;

    /** @var array<string, array{revision: int, generation: int, fileIdentity: string}> */
    private static array $processClearStates = [];

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

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $pendingMappings = [];

    private bool $clearPending = false;

    private int $baseGeneration = 0;

    private ?string $observedFileIdentity = null;

    private int $observedProcessClearRevision = 0;

    private float $nextFilesystemProbeAt = 0.0;

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
        $this->observedProcessClearRevision = self::$processClearStates[$this->cachePath]['revision'] ?? 0;
        $this->deferFilesystemProbe();

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
        $this->refreshAfterExternalClear();

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
        $this->refreshAfterExternalClear();

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
        $this->refreshAfterExternalClear(forceFilesystemProbe: true);
        $this->memoryCache->put($sourceType, $destinationType, $config);
        $this->pendingMappings[$sourceType . '->' . $destinationType] = $config;
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
        $this->pendingMappings = [];
        $this->clearPending = true;
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
        $lock = null;
        $wasClearPending = $this->clearPending;

        try {
            if ( ! $this->isTrustedPathTree($this->cachePath)) {
                return false;
            }

            $cacheDir = dirname($this->cachePath);
            if ( ! is_dir($cacheDir)) {
                if ( ! @mkdir($cacheDir, 0700, true) && ! is_dir($cacheDir)) {
                    return false;
                }
            }

            if ( ! $this->isTrustedPathTree($this->cachePath)) {
                return false;
            }

            $lock = $this->acquireLock();
            if ( ! is_resource($lock)) {
                return false;
            }

            $persistedState = ['generation' => 0, 'mappings' => []];
            if (is_file($this->cachePath)) {
                if ( ! $this->isTrustedCacheFile()) {
                    return false;
                }

                $persistedState = $this->readCacheState() ?? $persistedState;
            }

            $generation = $persistedState['generation'];
            $persistedMappings = $persistedState['mappings'];
            if ($this->clearPending) {
                $generation++;
                $persistedMappings = [];
            } elseif ($this->baseGeneration !== $generation) {
                $this->replaceMemoryMappings($persistedMappings);
            } else {
                foreach ($this->pendingMappings as $key => $config) {
                    if ($this->isSafeMappingConfig($config)) {
                        $persistedMappings[$key] = $config;
                    }
                }
            }

            $payload = [
                'version' => self::SCHEMA_VERSION,
                'generation' => $generation,
                'mappings' => $persistedMappings,
            ];
            $contents = json_encode($payload, JSON_THROW_ON_ERROR);
            $tmpFile = @tempnam($cacheDir, basename($this->cachePath) . '.tmp-');
            if (false === $tmpFile) {
                return false;
            }

            if (false === @file_put_contents($tmpFile, $contents, LOCK_EX)) {
                return false;
            }

            if ( ! @chmod($tmpFile, 0600)) {
                return false;
            }

            if ( ! @rename($tmpFile, $this->cachePath)) {
                return false;
            }

            $tmpFile = null;
            $this->isDirty = false;
            $this->pendingMappings = [];
            $this->clearPending = false;
            $this->baseGeneration = $generation;
            $this->observedFileIdentity = $this->cacheFileIdentity();
            $this->deferFilesystemProbe();
            if ($wasClearPending && null !== $this->observedFileIdentity) {
                $this->publishProcessClear($generation, $this->observedFileIdentity);
            }
            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if (is_string($tmpFile) && is_file($tmpFile)) {
                @unlink($tmpFile);
            }
            if (is_resource($lock)) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
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

        if ( ! $this->isTrustedCacheFile()) {
            return;
        }

        try {
            $state = $this->readCacheState();
            if (null === $state) {
                $this->memoryCache->clear();
                return;
            }

            $this->baseGeneration = $state['generation'];
            $this->replaceMemoryMappings($state['mappings']);
            $this->observedFileIdentity = $this->cacheFileIdentity();
        } catch (Throwable) {
            $this->memoryCache->clear();
        }
    }

    /**
     * @return array{generation: int, mappings: array<string, array<string, array<string, mixed>>>}|null
     */
    private function validatePayload(mixed $payload): ?array
    {
        if ( ! is_array($payload)) {
            return null;
        }

        $payloadKeys = array_keys($payload);
        sort($payloadKeys);
        if ( ! in_array($payloadKeys, [
            ['generation', 'mappings', 'version'],
            ['mappings', 'version'],
        ], true)
            || self::SCHEMA_VERSION !== ($payload['version'] ?? null)
            || ! is_array($payload['mappings'] ?? null)
            || ! is_int($payload['generation'] ?? 0)
            || 0 > ($payload['generation'] ?? 0)) {
            return null;
        }

        $mappings = [];
        foreach ($payload['mappings'] as $key => $config) {
            if ( ! is_string($key) || 2 !== count(explode('->', $key, 2)) || ! is_array($config) || ! $this->isSafeMappingConfig($config)) {
                continue;
            }

            [$sourceType, $destinationType] = explode('->', $key, 2);
            if ('' === $sourceType || '' === $destinationType || str_contains($destinationType, '->')) {
                continue;
            }

            $validatedConfig = [];
            foreach ($config as $property => $propertyConfig) {
                if ( ! is_string($property) || '' === $property || ! is_array($propertyConfig) || ! $this->isPersistableValue($propertyConfig)) {
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

        return [
            'generation' => $payload['generation'] ?? 0,
            'mappings' => $mappings,
        ];
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

        if (is_float($value)) {
            return is_finite($value);
        }

        if (is_string($value)) {
            return 1 === preg_match('//u', $value);
        }

        return null === $value || is_bool($value) || is_int($value);
    }

    /** @param array<array-key, mixed> $config */
    private function isSafeMappingConfig(array $config): bool
    {
        if ( ! $this->isPersistableValue($config)) {
            return false;
        }

        foreach ($config as $property => $propertyConfig) {
            if ( ! is_string($property) || '' === $property || ! is_array($propertyConfig)) {
                return false;
            }

            $requiredKeys = ['source', 'transformer', 'condition', 'default', 'hasDefault', 'ignore'];
            $allowedKeys = [...$requiredKeys, 'hasCarbonAttributes'];
            if ([] !== array_diff(array_keys($propertyConfig), $allowedKeys)
                || [] !== array_diff($requiredKeys, array_keys($propertyConfig))) {
                return false;
            }

            if ( ! is_string($propertyConfig['source'])
                || null !== $propertyConfig['transformer']
                || null !== $propertyConfig['condition']
                || ! is_bool($propertyConfig['hasDefault'])
                || ! is_bool($propertyConfig['ignore'])
                || ! $this->isPersistableValue($propertyConfig['default'])
                || (array_key_exists('hasCarbonAttributes', $propertyConfig)
                    && ! is_bool($propertyConfig['hasCarbonAttributes']))) {
                return false;
            }
        }

        return true;
    }

    private function isTrustedCacheFile(): bool
    {
        if ( ! $this->isTrustedPathTree($this->cachePath) || is_link($this->cachePath)) {
            return false;
        }

        clearstatcache(true, $this->cachePath);
        $permissions = @fileperms($this->cachePath);
        if (false === $permissions || 0 !== ($permissions & 0022)) {
            return false;
        }

        if (function_exists('posix_geteuid')) {
            $owner = @fileowner($this->cachePath);
            if (false === $owner || posix_geteuid() !== $owner) {
                return false;
            }
        }

        return true;
    }

    /** @return array{generation: int, mappings: array<string, array<string, array<string, mixed>>>}|null */
    private function readCacheState(): ?array
    {
        if ( ! $this->isTrustedCacheFile()) {
            return null;
        }

        clearstatcache(true, $this->cachePath);
        $before = $this->normalizeStat(@lstat($this->cachePath));
        $handle = @fopen($this->cachePath, 'rb');
        if (null === $before || false === $handle) {
            return null;
        }

        try {
            $opened = $this->normalizeStat(@fstat($handle));
            $after = $this->normalizeStat(@lstat($this->cachePath));
            if (null === $opened || null === $after
                || $before['dev'] !== $opened['dev']
                || $before['ino'] !== $opened['ino']
                || $after['dev'] !== $opened['dev']
                || $after['ino'] !== $opened['ino']
                || 0 !== ($opened['mode'] & 0022)
                || ! $this->isTrustedPathTree($this->cachePath)) {
                return null;
            }

            if (function_exists('posix_geteuid') && posix_geteuid() !== $opened['uid']) {
                return null;
            }

            $contents = stream_get_contents($handle);
            if (false === $contents) {
                return null;
            }

            return $this->validatePayload(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            return null;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string, array<string, array<string, mixed>>> $mappings */
    private function replaceMemoryMappings(array $mappings): void
    {
        $this->memoryCache->clear();
        foreach ($mappings as $key => $config) {
            [$sourceType, $destinationType] = explode('->', $key, 2);
            $this->memoryCache->put($sourceType, $destinationType, $config);
        }
    }

    private function refreshAfterExternalClear(bool $forceFilesystemProbe = false): void
    {
        $this->refreshAfterProcessClear();

        $now = (float) hrtime(true);
        if ( ! $forceFilesystemProbe && $now < $this->nextFilesystemProbeAt) {
            return;
        }
        $this->nextFilesystemProbeAt = $now + self::FILESYSTEM_PROBE_INTERVAL_NANOSECONDS;

        $fileIdentity = $this->cacheFileIdentity();
        if (null === $fileIdentity || $fileIdentity === $this->observedFileIdentity) {
            return;
        }

        $state = $this->readCacheState();
        if (null === $state) {
            return;
        }

        $this->observedFileIdentity = $fileIdentity;
        if ($state['generation'] === $this->baseGeneration) {
            return;
        }

        $this->replaceMemoryMappings($state['mappings']);
        $this->pendingMappings = [];
        $this->clearPending = false;
        $this->baseGeneration = $state['generation'];
        $this->publishProcessClear($state['generation'], $fileIdentity);
    }

    private function refreshAfterProcessClear(): void
    {
        $state = self::$processClearStates[$this->cachePath] ?? null;
        if (null === $state || $state['revision'] === $this->observedProcessClearRevision) {
            return;
        }

        $this->memoryCache->clear();
        $this->pendingMappings = [];
        $this->clearPending = false;
        $this->baseGeneration = $state['generation'];
        $this->observedFileIdentity = $state['fileIdentity'];
        $this->observedProcessClearRevision = $state['revision'];
        $this->deferFilesystemProbe();
    }

    private function publishProcessClear(int $generation, string $fileIdentity): void
    {
        $revision = (self::$processClearStates[$this->cachePath]['revision'] ?? 0) + 1;
        self::$processClearStates[$this->cachePath] = [
            'revision' => $revision,
            'generation' => $generation,
            'fileIdentity' => $fileIdentity,
        ];
        $this->observedProcessClearRevision = $revision;
    }

    private function deferFilesystemProbe(): void
    {
        $this->nextFilesystemProbeAt = (float) hrtime(true) + self::FILESYSTEM_PROBE_INTERVAL_NANOSECONDS;
    }

    private function cacheFileIdentity(): ?string
    {
        if ( ! is_file($this->cachePath)) {
            return null;
        }

        clearstatcache(true, $this->cachePath);
        $stat = $this->normalizeStat(@lstat($this->cachePath));
        if (null === $stat) {
            return null;
        }

        return $stat['dev'] . ':' . $stat['ino'];
    }

    /** @phpstan-impure */
    private function isTrustedPathTree(string $path): bool
    {
        $directory = dirname($path);
        $tempDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $stopDirectory = str_starts_with($path, $tempDirectory . DIRECTORY_SEPARATOR)
            ? $tempDirectory
            : null;

        while ($directory !== dirname($directory)) {
            clearstatcache(true, $directory);
            if (is_link($directory)) {
                return false;
            }

            if (is_dir($directory)) {
                $permissions = @fileperms($directory);
                if (false === $permissions) {
                    return false;
                }

                $isWritableByOthers = 0 !== ($permissions & 0022);
                $hasStickyBit = 0 !== ($permissions & 01000);
                $isTempRoot = $directory === $stopDirectory;
                if ($isWritableByOthers && ( ! $isTempRoot || ! $hasStickyBit)) {
                    return false;
                }

                if ( ! $isTempRoot && null !== $stopDirectory && function_exists('posix_geteuid')) {
                    $owner = @fileowner($directory);
                    if (false === $owner || posix_geteuid() !== $owner) {
                        return false;
                    }
                }
            }

            if ($directory === $stopDirectory) {
                break;
            }

            $directory = dirname($directory);
        }

        return ! is_link($path);
    }

    /** @return resource|false */
    private function acquireLock(): mixed
    {
        $lockDirectory = sys_get_temp_dir() . '/granite-mapper-locks/'
            . (function_exists('posix_geteuid') ? (string) posix_geteuid() : 'user');
        if ( ! $this->isTrustedPathTree($lockDirectory)
            || is_link($lockDirectory)
            || ( ! is_dir($lockDirectory) && ! @mkdir($lockDirectory, 0700, true) && ! is_dir($lockDirectory))) {
            return false;
        }

        if ( ! $this->isTrustedPathTree($lockDirectory)) {
            return false;
        }

        $permissions = @fileperms($lockDirectory);
        if (false === $permissions || 0 !== ($permissions & 0077)) {
            return false;
        }

        if (function_exists('posix_geteuid')) {
            $owner = @fileowner($lockDirectory);
            if (false === $owner || posix_geteuid() !== $owner) {
                return false;
            }
        }

        $lockPath = $lockDirectory . '/' . hash('sha256', $this->cachePath) . '.lock';
        if ( ! $this->isTrustedPathTree($lockPath) || is_link($lockPath)) {
            return false;
        }

        $lock = @fopen($lockPath, 'c+b');
        if (false === $lock || ! @chmod($lockPath, 0600) || ! @flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                @fclose($lock);
            }
            return false;
        }

        $stat = $this->normalizeStat(@fstat($lock));
        if (null === $stat || 0 !== ($stat['mode'] & 0077)
            || (function_exists('posix_geteuid') && posix_geteuid() !== $stat['uid'])) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            return false;
        }

        return $lock;
    }

    /** @return array{dev: int, ino: int, mode: int, uid: int}|null */
    private function normalizeStat(mixed $stat): ?array
    {
        if ( ! is_array($stat)
            || ! isset($stat['dev'], $stat['ino'], $stat['mode'], $stat['uid'])
            || ! is_int($stat['dev'])
            || ! is_int($stat['ino'])
            || ! is_int($stat['mode'])
            || ! is_int($stat['uid'])) {
            return null;
        }

        return [
            'dev' => $stat['dev'],
            'ino' => $stat['ino'],
            'mode' => $stat['mode'],
            'uid' => $stat['uid'],
        ];
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

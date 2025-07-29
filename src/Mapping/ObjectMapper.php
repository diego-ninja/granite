<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Mapping\Cache\CacheFactory;
use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Core\ConfigurationBuilder;
use Ninja\Granite\Mapping\Core\MappingEngine;
use Ninja\Granite\Mapping\Exceptions\MappingException;

/**
 * Main ObjectMapper facade providing a clean, fluent API.
 * Delegates actual work to specialized components.
 */
final class ObjectMapper implements Mapper, MappingStorage
{
    private MappingEngine $engine;
    private ConfigurationBuilder $configBuilder;
    private MappingCache $cache;
    private array $profiles = [];

    // Singleton support
    private static ?self $globalInstance = null;
    private static bool $isConfigured = false;

    /**
     * @throws ReflectionException
     */
    public function __construct(?MapperConfig $config = null)
    {
        $config ??= MapperConfig::default();

        $this->cache = CacheFactory::create($config->cacheType);
        $this->configBuilder = new ConfigurationBuilder(
            $this->cache,
            $config->useConventions,
            $config->conventionThreshold,
        );
        $this->engine = new MappingEngine($this->configBuilder);

        $this->registerProfiles($config->profiles);

        if ($config->warmupCache) {
            $this->warmupCache();
        }
    }

    // ================
    // Global Instance
    // ================

    /**
     * @throws ReflectionException
     */
    public static function getInstance(): self
    {
        if (null === self::$globalInstance) {
            self::$globalInstance = new self(
                MapperConfig::default()
                    ->withCacheType(CacheType::Shared)
                    ->withConventions(true, 0.75)
                    ->withoutWarmup(),
            );
        }

        return self::$globalInstance;
    }

    /**
     * @throws ReflectionException
     */
    public static function configure(callable $configurator): void
    {
        $config = MapperConfig::default();
        $configurator($config);

        self::$globalInstance = new self($config);
        self::$isConfigured = true;
    }

    public static function isConfigured(): bool
    {
        return self::$isConfigured;
    }

    public static function reset(): void
    {
        self::$globalInstance = null;
        self::$isConfigured = false;
    }

    public static function setGlobalInstance(self $instance): void
    {
        self::$globalInstance = $instance;
        self::$isConfigured = true;
    }

    // =================
    // Core Mapping API
    // =================

    /**
     * @template T of object
     * @param mixed $source
     * @param class-string<T> $destinationType
     * @return T
     * @throws GraniteException
     * @throws MappingException
     */
    public function map(mixed $source, string $destinationType): object
    {
        /** @var T */
        return $this->engine->map($source, $destinationType);
    }

    /**
     * @throws MappingException
     */
    public function mapTo(mixed $source, object $destination): object
    {
        return $this->engine->mapTo($source, $destination);
    }

    /**
     * @template T of object
     * @param array $source Source array
     * @param class-string<T> $destinationType Destination type
     * @return array<T> Array of mapped objects
     * @throws GraniteException
     * @throws MappingException
     */
    public function mapArray(array $source, string $destinationType): array
    {
        return array_map(
            fn($item) => $this->map($item, $destinationType),
            $source,
        );
    }

    // ======================
    // Mapping Configuration
    // ======================

    /**
     * @param class-string $sourceType Source type
     * @param class-string $destinationType Destination type
     */
    public function createMap(string $sourceType, string $destinationType): TypeMapping
    {
        return new TypeMapping($this, $sourceType, $destinationType);
    }

    /**
     * @param class-string $typeA Type A
     * @param class-string $typeB Type B
     */
    public function createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping
    {
        return new BidirectionalTypeMapping($this, $typeA, $typeB);
    }

    /**
     * @param class-string $sourceType
     * @param class-string $destinationType
     * @throws MappingException
     */
    public function createReverseMap(string $sourceType, string $destinationType): TypeMapping
    {
        $reverseMapping = $this->createMap($destinationType, $sourceType);
        $this->configBuilder->createReverseConfiguration($sourceType, $destinationType, $reverseMapping);
        return $reverseMapping;
    }

    // ==================
    // Profile Management
    // ==================

    public function addProfile(MappingProfile $profile): self
    {
        $this->profiles[] = $profile;
        $this->configBuilder->addProfile($profile);
        return $this;
    }

    // =================
    // Convention System
    // =================

    public function useConventions(bool $enabled = true): self
    {
        $this->configBuilder->enableConventions($enabled);
        return $this;
    }

    public function setConventionThreshold(float $threshold): self
    {
        $this->configBuilder->setConventionThreshold($threshold);
        return $this;
    }

    public function registerConvention(NamingConvention $convention): self
    {
        $this->configBuilder->registerConvention($convention);
        return $this;
    }

    // ==============
    // Cache Management
    // ==============

    public function clearCache(): self
    {
        $this->cache->clear();
        $this->configBuilder->clearCache();
        return $this;
    }

    public function warmupCache(): self
    {
        $this->configBuilder->warmupCache($this->profiles);
        return $this;
    }

    public function getCache(): MappingCache
    {
        return $this->cache;
    }

    // ======================
    // MappingStorage Interface
    // ======================

    public function addPropertyMapping(string $sourceType, string $destinationType, string $property, PropertyMapping $mapping): void
    {
        $this->configBuilder->addPropertyMapping($sourceType, $destinationType, $property, $mapping);
    }

    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        return $this->configBuilder->getMapping($sourceType, $destinationType, $property);
    }

    public function getMappingsForTypes(string $sourceType, string $destinationType): array
    {
        return $this->configBuilder->getMappingsForTypes($sourceType, $destinationType);
    }

    private function registerProfiles(array $profiles): void
    {
        foreach ($profiles as $profile) {
            if ($profile instanceof MappingProfile) {
                $this->addProfile($profile);
            }
        }
    }
}

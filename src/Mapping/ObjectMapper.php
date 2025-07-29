<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Core\MappingEngine;
use Ninja\Granite\Mapping\Core\ConfigurationBuilder;
use Ninja\Granite\Mapping\Cache\CacheFactory;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Monads\Contracts\Either as EitherContract;
use Ninja\Granite\Monads\Factories\Either;
use Ninja\Granite\Monads\Pair;
use RuntimeException;

/**
 * Main ObjectMapper facade providing a clean, fluent API.
 * Delegates work to specialized components.
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

    public function __construct(?MapperConfig $config = null)
    {
        $config ??= MapperConfig::default();

        $this->cache = CacheFactory::create($config->cacheType);
        $this->configBuilder = new ConfigurationBuilder(
            $this->cache,
            $config->useConventions,
            $config->conventionThreshold
        );
        $this->engine = new MappingEngine($this->configBuilder);

        $this->registerProfiles($config->profiles);

        if ($config->warmupCache) {
            $this->warmupCache();
        }
    }

    // =================
    // Core Mapping API
    // =================

    public function map(mixed $source, string $destinationType): EitherContract
    {
        return Either::fromCallable(function() use ($source, $destinationType) {
            // Simplified mapping logic - this would call the actual mapping engine
            if (!class_exists($destinationType)) {
                throw new RuntimeException("Destination type '$destinationType' does not exist");
            }

            if (is_subclass_of($destinationType, GraniteObject::class)) {
                return $destinationType::from($source)->fold(
                    fn($error) => throw new RuntimeException("Failed to create $destinationType: " . json_encode($error)),
                    fn($result) => $result
                );
            }

            throw new RuntimeException("Mapping to '$destinationType' not supported yet");
        });
    }

    /**
     * @throws MappingException
     */
    public function mapTo(mixed $source, object $destination): object
    {
        return $this->engine->mapTo($source, $destination);
    }

    public function mapArray(array $sources, string $destinationType): EitherContract
    {
        $results = [];
        foreach ($sources as $index => $source) {
            $result = $this->map($source, $destinationType);
            if ($result->isLeft()) {
                return Either::left("Error at index $index: " . $result->getLeft());
            }
            $results[] = $result->getRight();
        }
        return Either::right($results);
    }

    public function mapWithMetrics(mixed $source, string $destinationType): Pair
    {
        $startTime = microtime(true);
        $result = $this->map($source, $destinationType);

        $metrics = [
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'success' => $result->isRight(),
            'destination_type' => $destinationType,
            'source_type' => is_object($source) ? get_class($source) : gettype($source)
        ];

        return Pair::of($result, $metrics);
    }

    public function createMap(string $sourceType, string $destinationType): TypeMapping
    {
        return new TypeMapping($this, $sourceType, $destinationType);
    }

    public function createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping
    {
        return new BidirectionalTypeMapping($this, $typeA, $typeB);
    }

    /**
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

    private function registerProfiles(array $profiles): void
    {
        foreach ($profiles as $profile) {
            $this->addProfile($profile);
        }
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

    // ================
    // Global Instance
    // ================

    public static function getInstance(): self
    {
        if (self::$globalInstance === null) {
            self::$globalInstance = new self(
                MapperConfig::default()
                    ->withCacheType(CacheType::Shared)
                    ->withConventions(true, 0.75)
                    ->withoutWarmup()
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
}
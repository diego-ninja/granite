<?php

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\ConventionMapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\Traits\MappingStorageTrait;
use Ninja\Granite\Mapping\TypeMapping;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionProperty;

/**
 * Builds and manages mapping configurations.
 * Handles profile registration, convention mapping, and caching.
 */
final class ConfigurationBuilder
{
    use MappingStorageTrait;

    private MappingCache $cache;
    private ConventionMapper $conventionMapper;
    /** @var array<int, MappingProfile> */
    private array $profiles = [];
    private bool $useConventions;

    /** @param array<int, mixed> $conventions */
    public function __construct(
        MappingCache $cache,
        bool $useConventions = false,
        float $conventionThreshold = 0.8,
        array $conventions = [],
    ) {
        $this->cache = $cache;
        $this->useConventions = $useConventions;
        $this->conventionMapper = new ConventionMapper(null, $conventionThreshold);
        foreach ($conventions as $convention) {
            if ( ! $convention instanceof NamingConvention) {
                continue;
            }
            $this->conventionMapper->registerConvention($convention);
        }
    }

    /**
     * Get mapping configuration for source to destination.
     * @return array<string, array<string, mixed>>
     */
    public function getConfiguration(mixed $source, string $destinationType): array
    {
        $sourceType = is_object($source) ? get_class($source) : 'array';

        // Check cache first
        if ($this->cache->has($sourceType, $destinationType)) {
            return $this->cache->get($sourceType, $destinationType) ?? [];
        }

        // Build new configuration
        $config = $this->buildConfiguration($sourceType, $destinationType);

        // Cache it
        $this->cache->put($sourceType, $destinationType, $config);

        return $config;
    }

    /**
     * Create reverse configuration for existing mapping.
     * @throws MappingException
     */
    public function createReverseConfiguration(string $sourceType, string $destinationType, TypeMapping $reverseMapping): void
    {
        $originalConfig = $this->getConfiguration($sourceType, $destinationType);

        foreach ($originalConfig as $destProp => $config) {
            $sourceProp = $config['source'] ?? null;

            // Skip if no explicit source property or complex transformers
            if ( ! is_string($sourceProp) || $sourceProp === $destProp || ($config['transformer'] ?? null) !== null) {
                continue;
            }

            // Create reverse mapping
            $reverseMapping->forMember($sourceProp, fn(PropertyMapping $mapping) => $mapping->mapFrom($destProp));
        }
    }

    // =================
    // Profile Management
    // =================

    public function addProfile(MappingProfile $profile): void
    {
        $this->profiles[] = $profile;
        $this->invalidateConfigurationCaches();
    }

    public function addPropertyMapping(
        string $sourceType,
        string $destinationType,
        string $property,
        PropertyMapping $mapping,
    ): void {
        $key = $sourceType . '->' . $destinationType;
        $this->mappings[$key][$property] = $mapping;
        $this->invalidateConfigurationCaches();
    }

    /** @param array<int, mixed> $profiles */
    public function warmupCache(array $profiles): void
    {
        foreach ($profiles as $profile) {
            if ($profile instanceof MappingProfile) {
                $this->warmupProfileCache($profile);
            }
        }
    }

    // ===================
    // Convention Management
    // ===================

    public function enableConventions(bool $enabled): void
    {
        $this->useConventions = $enabled;
        $this->invalidateConfigurationCaches();
    }

    public function setConventionThreshold(float $threshold): void
    {
        $this->conventionMapper->setConfidenceThreshold($threshold);
        $this->invalidateConfigurationCaches();
    }

    public function registerConvention(NamingConvention $convention): void
    {
        $this->conventionMapper->registerConvention($convention);
        $this->invalidateConfigurationCaches();
    }

    // ==============
    // Cache Management
    // ==============

    public function clearCache(): void
    {
        $this->cache->clear();
        $this->conventionMapper->clearMappingsCache();
    }

    private function invalidateConfigurationCaches(): void
    {
        $this->cache->clear();
        $this->conventionMapper->clearMappingsCache();
    }

    /**
     * Build mapping configuration from profiles and conventions.
     * @return array<string, array<string, mixed>>
     */
    private function buildConfiguration(string $sourceType, string $destinationType): array
    {
        $config = [];

        // Get destination properties
        $properties = $this->getDestinationProperties($destinationType);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Check for explicit mapping from profiles
            $mapping = $this->findExplicitMapping($sourceType, $destinationType, $propertyName);

            if (null !== $mapping) {
                $config[$propertyName] = $this->buildPropertyConfig($mapping, $propertyName);
            } else {
                // Build from attributes or conventions
                $config[$propertyName] = $this->buildPropertyFromAttributes($property, $sourceType, $destinationType);
            }
        }

        // Apply convention-based mapping if enabled
        if ($this->useConventions) {
            $config = $this->applyConventionMappings($sourceType, $destinationType, $config);
        }

        return $config;
    }

    /**
     * Find explicit mapping from registered profiles.
     */
    private function findExplicitMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        // Check direct mappings first
        $mapping = $this->getMapping($sourceType, $destinationType, $property);
        if (null !== $mapping) {
            return $mapping;
        }

        // Check profiles
        foreach ($this->profiles as $profile) {
            $mapping = $profile->getMapping($sourceType, $destinationType, $property);
            if ($mapping instanceof PropertyMapping) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Build property configuration from PropertyMapping.
     */
    /** @return array<string, mixed> */
    private function buildPropertyConfig(PropertyMapping $mapping, string $propertyName): array
    {
        return $mapping->toConfig($propertyName);
    }

    /**
     * Build property configuration from attributes.
     */
    /** @return array<string, mixed> */
    private function buildPropertyFromAttributes(ReflectionProperty $property, string $sourceType, string $destinationType): array
    {
        $attributeProcessor = new AttributeProcessor();
        return $attributeProcessor->processProperty($property);
    }

    /**
     * Apply convention-based mappings.
     */
    /** @param array<string, array<string, mixed>> $config
     * @return array<string, array<string, mixed>>
     */
    private function applyConventionMappings(string $sourceType, string $destinationType, array $config): array
    {
        if ('array' === $sourceType || ! class_exists($sourceType) || ! class_exists($destinationType)) {
            return $config;
        }

        $conventionMappings = $this->conventionMapper->discoverMappings($sourceType, $destinationType);

        foreach ($conventionMappings as $destProperty => $sourceProperty) {
            // Only apply if no explicit mapping exists
            if ( ! isset($config[$destProperty]) || $config[$destProperty]['source'] === $destProperty) {
                $config[$destProperty]['source'] = $sourceProperty;
            }
        }

        return $config;
    }

    /**
     * Get destination type properties.
     * @param string $destinationType
     * @throws ReflectionException
     */
    /** @return array<int, ReflectionProperty> */
    private function getDestinationProperties(string $destinationType): array
    {
        if ( ! class_exists($destinationType)) {
            return [];
        }

        return array_values(ReflectionCache::getPublicProperties($destinationType));
    }

    private function warmupProfileCache(MappingProfile $profile): void
    {
        foreach ($profile->configuredTypePairs() as [$sourceType, $destinationType]) {
            if ( ! $this->cache->has($sourceType, $destinationType)) {
                $config = $this->buildConfiguration($sourceType, $destinationType);
                $this->cache->put($sourceType, $destinationType, $config);
            }
        }
    }
}

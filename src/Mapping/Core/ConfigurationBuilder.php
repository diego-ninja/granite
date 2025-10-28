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
use ReflectionClass;
use ReflectionProperty;

/**
 * Builds and manages mapping configurations.
 * Handles profile registration, convention mapping, and caching.
 */
final class ConfigurationBuilder
{
    use MappingStorageTrait;

    private MappingCache $cache;
    private ?ConventionMapper $conventionMapper;
    private array $profiles = [];
    private bool $useConventions;

    public function __construct(
        MappingCache $cache,
        bool $useConventions = false,
        float $conventionThreshold = 0.8,
    ) {
        $this->cache = $cache;
        $this->useConventions = $useConventions;
        $this->conventionMapper = $useConventions
            ? new ConventionMapper(null, $conventionThreshold)
            : null;
    }

    /**
     * Get mapping configuration for source to destination.
     */
    public function getConfiguration(mixed $source, string $destinationType): array
    {
        $sourceType = is_object($source) ? get_class($source) : 'array';

        // Check cache first
        if ($this->cache->has($sourceType, $destinationType)) {
            $cached = $this->cache->get($sourceType, $destinationType);
            return is_array($cached) ? $cached : [];
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
            if ( ! is_array($config)) {
                continue;
            }

            $sourceProp = $config['source'] ?? null;

            // Skip if no explicit source property or complex transformers
            if (null === $sourceProp || $sourceProp === $destProp || ($config['transformer'] ?? null) !== null) {
                continue;
            }

            if (is_string($sourceProp) && is_string($destProp)) {
                // Create reverse mapping
                $reverseMapping->forMember($sourceProp, fn($m) => $m->mapFrom($destProp));
            }
        }
    }

    // =================
    // Profile Management
    // =================

    public function addProfile(MappingProfile $profile): void
    {
        $this->profiles[] = $profile;
    }

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

        if ($enabled && null === $this->conventionMapper) {
            $this->conventionMapper = new ConventionMapper();
        }
    }

    public function setConventionThreshold(float $threshold): void
    {
        $this->conventionMapper?->setConfidenceThreshold($threshold);
    }

    public function registerConvention(NamingConvention $convention): void
    {
        $this->conventionMapper?->registerConvention($convention);
    }

    // ==============
    // Cache Management
    // ==============

    public function clearCache(): void
    {
        $this->conventionMapper?->clearMappingsCache();
    }

    /**
     * Build mapping configuration from profiles and conventions.
     */
    private function buildConfiguration(string $sourceType, string $destinationType): array
    {
        $config = [];

        // Get destination properties
        $properties = $this->getDestinationProperties($destinationType);

        foreach ($properties as $property) {
            if ( ! ($property instanceof ReflectionProperty)) {
                continue;
            }

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
        if ($this->useConventions && null !== $this->conventionMapper) {
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
    private function buildPropertyConfig(PropertyMapping $mapping, string $propertyName): array
    {
        return [
            'source' => $mapping->getSourceProperty() ?? $propertyName,
            'transformer' => $mapping->getTransformer(),
            'condition' => $mapping->getCondition(),
            'default' => $mapping->getDefaultValue(),
            'hasDefault' => $mapping->hasDefaultValue(),
            'ignore' => $mapping->isIgnored(),
        ];
    }

    /**
     * Build property configuration from attributes.
     */
    private function buildPropertyFromAttributes(ReflectionProperty $property, string $sourceType, string $destinationType): array
    {
        $attributeProcessor = new AttributeProcessor();
        return $attributeProcessor->processProperty($property);
    }

    /**
     * Apply convention-based mappings.
     */
    private function applyConventionMappings(string $sourceType, string $destinationType, array $config): array
    {
        if ('array' === $sourceType || ! class_exists($sourceType) || ! class_exists($destinationType) || null === $this->conventionMapper) {
            return $config;
        }

        $conventionMappings = $this->conventionMapper->discoverMappings($sourceType, $destinationType);

        foreach ($conventionMappings as $destProperty => $sourceProperty) {
            // Only apply if no explicit mapping exists
            if ( ! isset($config[$destProperty]) || (
                is_array($config[$destProperty])
                && isset($config[$destProperty]['source'])
                && $config[$destProperty]['source'] === $destProperty
            )) {
                if ( ! is_array($config[$destProperty])) {
                    $config[$destProperty] = [];
                }
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
    private function getDestinationProperties(string $destinationType): array
    {
        if ( ! class_exists($destinationType)) {
            return [];
        }

        return ReflectionCache::getPublicProperties($destinationType);
    }

    private function warmupProfileCache(MappingProfile $profile): void
    {
        // Extract mappings from profile and warm up cache
        $reflection = new ReflectionClass($profile);
        $mappingsProperty = $reflection->getProperty('mappings');
        $mappingsProperty->setAccessible(true);

        $mappings = $mappingsProperty->getValue($profile);

        if ( ! is_array($mappings)) {
            return;
        }

        foreach ($mappings as $key => $propertyMappings) {
            if ( ! is_string($key)) {
                continue;
            }
            [$sourceType, $destinationType] = explode('->', $key);

            if ( ! $this->cache->has($sourceType, $destinationType)) {
                $config = $this->buildConfiguration($sourceType, $destinationType);
                $this->cache->put($sourceType, $destinationType, $config);
            }
        }
    }
}

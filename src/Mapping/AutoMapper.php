<?php

namespace Ninja\Granite\Mapping;

// src/Mapping/AutoMapper.php
namespace Ninja\Granite\Mapping;

use Exception;
use Ninja\Granite\Contracts\Mapper;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Contracts\Transformer;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\Attributes\Ignore;
use ReflectionException;
use ReflectionProperty;

class AutoMapper implements Mapper
{
    /**
     * Registered mapping profiles.
     *
     * @var MappingProfile[]
     */
    private array $profiles = [];

    /**
     * Cache for mapping configurations.
     *
     * @var array<string, array>
     */
    private array $mappingCache = [];

    public function __construct(array $profiles = [])
    {
        foreach ($profiles as $profile) {
            $this->addProfile($profile);
        }
    }

    /**
     * Add a mapping profile.
     */
    public function addProfile(MappingProfile $profile): self
    {
        $this->profiles[] = $profile;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws MappingException
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     * @throws GraniteException
     */
    public function map(mixed $source, string $destinationType): object
    {
        if (!class_exists($destinationType)) {
            throw MappingException::destinationTypeNotFound($destinationType);
        }

        try {
            $sourceData = $this->normalizeSource($source);

            // Get mapping configuration
            $mappingConfig = $this->getMappingConfiguration($source, $destinationType);

            // Apply transformations
            $transformedData = $this->applyMappings($sourceData, $mappingConfig);

            // Create destination object
            if (is_subclass_of($destinationType, GraniteObject::class)) {
                return $destinationType::from($transformedData);
            }

            return $this->createObjectFromArray($transformedData, $destinationType);
        } catch (GraniteException $e) {
            throw $e;
        } catch (Exception $e) {
            $sourceType = is_object($source) ? get_class($source) : gettype($source);
            throw new MappingException($sourceType, $destinationType, $e->getMessage(), null, 0, $e);        }
    }

    /**
     * {@inheritdoc}
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     * @throws MappingException
     */
    public function mapTo(mixed $source, object $destination): object
    {
        $sourceData = $this->normalizeSource($source);
        $destinationType = get_class($destination);

        $mappingConfig = $this->getMappingConfiguration($source, $destinationType);
        $transformedData = $this->applyMappings($sourceData, $mappingConfig);

        return $this->populateObject($destination, $transformedData);
    }

    /**
     * {@inheritdoc}
     * @throws \Ninja\Granite\Exceptions\ReflectionException|MappingException|GraniteException
     */
    public function mapArray(array $source, string $destinationType): array
    {
        $result = [];
        foreach ($source as $item) {
            $result[] = $this->map($item, $destinationType);
        }
        return $result;
    }

    /**
     * Normalize source data to array format.
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     * @throws MappingException
     */
    private function normalizeSource(mixed $source): array
    {
        if (is_array($source)) {
            return $source;
        }

        if ($source instanceof GraniteObject) {
            return $source->array();
        }

        if (is_object($source)) {
            return $this->objectToArray($source);
        }

        throw MappingException::unsupportedSourceType($source);
    }

    /**
     * Convert object to array using reflection.
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private function objectToArray(object $source): array
    {
        $result = [];
        $properties = ReflectionCache::getPublicProperties(get_class($source));

        foreach ($properties as $property) {
            if ($property->isInitialized($source)) {
                $result[$property->getName()] = $property->getValue($source);
            }
        }

        return $result;
    }

    /**
     * Get mapping configuration for source to destination.
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private function getMappingConfiguration(mixed $source, string $destinationType): array
    {
        $sourceType = is_object($source) ? get_class($source) : 'array';
        $cacheKey = $sourceType . '->' . $destinationType;

        if (isset($this->mappingCache[$cacheKey])) {
            return $this->mappingCache[$cacheKey];
        }

        $config = $this->buildMappingConfiguration($sourceType, $destinationType);
        $this->mappingCache[$cacheKey] = $config;

        return $config;
    }

    /**
     * Build mapping configuration.
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private function buildMappingConfiguration(string $sourceType, string $destinationType): array
    {
        $config = [];

        $destinationProperties = ReflectionCache::getPublicProperties($destinationType);

        foreach ($destinationProperties as $property) {
            $propertyName = $property->getName();
            $mappingInfo = $this->getPropertyMappingInfo($property);

            if ($mappingInfo['ignore']) {
                continue;
            }

            // Get profile mapping first
            $profileMapping = $this->getProfileMapping($sourceType, $destinationType, $propertyName);

            // Determine source property
            $source = $mappingInfo['source'] ?? $propertyName;

            // If profile mapping has a source defined, use that instead
            if ($profileMapping !== null && $profileMapping->getSourceProperty() !== null) {
                $source = $profileMapping->getSourceProperty();
            }

            $config[$propertyName] = [
                'source' => $source,
                'transformer' => $mappingInfo['transformer'] ?? null,
                'profile_mapping' => $profileMapping
            ];
        }

        return $config;
    }
    /**
     * Get property mapping info from attributes.
     */
    private function getPropertyMappingInfo(ReflectionProperty $property): array
    {
        $info = [
            'ignore' => false,
            'source' => null,
            'transformer' => null
        ];

        $attributes = $property->getAttributes();

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof Ignore) {
                $info['ignore'] = true;
            } elseif ($instance instanceof MapFrom) {
                $info['source'] = $instance->source;
            } elseif ($instance instanceof MapWith) {
                $info['transformer'] = $instance->transformer;
            }
        }

        return $info;
    }

    /**
     * Get mapping from profiles.
     */
    private function getProfileMapping(string $sourceType, string $destinationType, string $propertyName): ?PropertyMapping
    {
        foreach ($this->profiles as $profile) {
            $mapping = $profile->getMapping($sourceType, $destinationType, $propertyName);
            if ($mapping !== null) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Apply mappings to source data.
     */
    private function applyMappings(array $sourceData, array $mappingConfig): array
    {
        $result = [];

        foreach ($mappingConfig as $destinationProperty => $config) {
            $sourceKey = $config['source'];
            $transformer = $config['transformer'];
            $profileMapping = $config['profile_mapping'];

            // Get source value
            $sourceValue = $this->getSourceValue($sourceData, $sourceKey);

            // Apply transformations
            if ($transformer !== null) {
                $sourceValue = $this->applyTransformer($sourceValue, $transformer);
            } elseif ($profileMapping !== null) {
                $sourceValue = $profileMapping->transform($sourceValue, $sourceData);
            }

            $result[$destinationProperty] = $sourceValue;
        }

        return $result;
    }

    /**
     * Get source value using dot notation support.
     */
    private function getSourceValue(array $sourceData, string $key): mixed
    {
        if (str_contains($key, '.')) {
            return $this->getNestedValue($sourceData, $key);
        }

        return $sourceData[$key] ?? null;
    }

    /**
     * Get nested value using dot notation.
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Apply transformer to value.
     */
    private function applyTransformer(mixed $value, mixed $transformer): mixed
    {
        if (is_callable($transformer)) {
            return $transformer($value);
        }

        if ($transformer instanceof Transformer) {
            return $transformer->transform($value);
        }

        return $value;
    }

    /**
     * Create object from array data.
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private function createObjectFromArray(array $data, string $className): object
    {
        try {
            $reflection = ReflectionCache::getClass($className);
            $instance = $reflection->newInstanceWithoutConstructor();

            return $this->populateObject($instance, $data);
        } catch (ReflectionException $e) {
            throw new \Ninja\Granite\Exceptions\ReflectionException($className, "Unable to create instance", $e->getMessage());
        }
    }

    /**
     * Populate an existing object with data.
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private function populateObject(object $object, array $data): object
    {
        $properties = ReflectionCache::getPublicProperties(get_class($object));

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if (array_key_exists($propertyName, $data)) {
                $property->setValue($object, $data[$propertyName]);
            }
        }

        return $object;
    }
}
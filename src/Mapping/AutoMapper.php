<?php

namespace Ninja\Granite\Mapping;

use Exception;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Attributes\MapBidirectional;
use Ninja\Granite\Mapping\Attributes\MapCollection;
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWhen;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\Cache\CacheFactory;
use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\Traits\MappingStorageTrait;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class AutoMapper implements Mapper, MappingStorage
{
    use MappingStorageTrait;

    /**
     * Registered mapping profiles.
     *
     * @var MappingProfile[]
     */
    private array $profiles = [];

    /**
     * Mapping configuration cache.
     */
    private MappingCache $cache;

    /**
     * Whether to warm up the cache with profiles.
     */
    private bool $warmupCache;

    /**
     * Constructor.
     *
     * @param array $profiles Optional mapping profiles to register
     * @param string $cacheType Cache type ('memory', 'shared', 'persistent')
     * @param bool $warmupCache Whether to warm up the cache with profiles
     */
    public function __construct(
        array $profiles = [],
        string $cacheType = 'shared',
        bool $warmupCache = true
    ) {
        $this->cache = CacheFactory::create($cacheType);
        $this->warmupCache = $warmupCache;

        foreach ($profiles as $profile) {
            $this->addProfile($profile);
        }

        if ($warmupCache && !empty($profiles)) {
            $this->warmupCache();
        }
    }

    /**
     * Add a mapping profile.
     *
     * @param MappingProfile $profile Mapping profile to add
     * @return $this For method chaining
     */
    public function addProfile(MappingProfile $profile): self
    {
        $this->profiles[] = $profile;

        if ($this->warmupCache) {
            $this->warmupProfileCache($profile);
        }

        return $this;
    }

    /**
     * Warm up cache with all profiles.
     *
     * @return void
     */
    private function warmupCache(): void
    {
        foreach ($this->profiles as $profile) {
            $this->warmupProfileCache($profile);
        }
    }

    /**
     * Warm up cache with a specific profile.
     *
     * @param MappingProfile $profile Mapping profile
     * @return void
     */
    private function warmupProfileCache(MappingProfile $profile): void
    {
        // Reflection to get all mappings from profile
        $reflection = new ReflectionClass($profile);
        $mappingsProperty = $reflection->getProperty('mappings');
        $mappingsProperty->setAccessible(true);

        $mappings = $mappingsProperty->getValue($profile);

        // Extract and cache configurations
        foreach ($mappings as $key => $propertyMappings) {
            list($sourceType, $destinationType) = explode('->', $key);

            // Only cache if not already cached
            if (!$this->cache->has($sourceType, $destinationType)) {
                $config = $this->buildMappingConfiguration($sourceType, $destinationType);
                $this->cache->put($sourceType, $destinationType, $config);
            }
        }
    }

    /**
     * Create a new mapping from source to destination.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return TypeMapping Type mapping configuration
     */
    public function createMap(string $sourceType, string $destinationType): TypeMapping
    {
        return new TypeMapping($this, $sourceType, $destinationType);
    }

    /**
     * Create bidirectional mappings between two types.
     *
     * @param string $typeA First type name
     * @param string $typeB Second type name
     * @return BidirectionalTypeMapping Bidirectional mapping configuration
     */
    public function createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping
    {
        return new BidirectionalTypeMapping($this, $typeA, $typeB);
    }

    /**
     * Creates a reverse mapping based on an existing mapping.
     *
     * @param string $sourceType Original source type
     * @param string $destinationType Original destination type
     * @return TypeMapping The reverse mapping
     * @throws MappingException If the original mapping doesn't exist
     */
    public function createReverseMap(string $sourceType, string $destinationType): TypeMapping
    {
        // Check if we have the original mapping
        $cacheKey = $sourceType . '->' . $destinationType;
        if (!isset($this->mappingCache[$cacheKey])) {
            throw new MappingException(
                $sourceType,
                $destinationType,
                "Cannot create reverse mapping: original mapping does not exist"
            );
        }

        // Get original mapping config
        $originalConfig = $this->mappingCache[$cacheKey];

        // Create the reverse mapping
        $reverseMapping = $this->createMap($destinationType, $sourceType);

        // Loop through original mappings and create reverse mappings
        foreach ($originalConfig as $destProp => $config) {
            $sourceProp = $config['source'];

            // Skip if no explicit source property (we can't reverse implicit mappings)
            if ($sourceProp === null || $sourceProp === $destProp) {
                continue;
            }

            // Skip properties with complex transformers (can't reverse automatically)
            if ($config['transformer'] !== null) {
                continue;
            }

            // Create reverse mapping
            $reverseMapping->forMember($sourceProp, fn($m) => $m->mapFrom($destProp));
        }

        return $reverseMapping->seal();
    }

    /**
     * Map from source object/array to destination type.
     *
     * @template T
     * @param mixed $source Source data
     * @param string $destinationType Destination class name
     * @return T Mapped object
     * @throws MappingException|GraniteException If mapping fails
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
            throw new MappingException($sourceType, $destinationType, $e->getMessage(), null, 0, $e);
        }
    }

    /**
     * Map from source to an existing destination object.
     *
     * @param mixed $source Source data
     * @param object $destination Destination object to populate
     * @return object Updated destination object
     * @throws MappingException If mapping fails
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
     * Map array of objects.
     *
     * @template T
     * @param array $source Array of source objects
     * @param string $destinationType Destination class name
     * @return T[] Array of mapped objects
     * @throws MappingException|GraniteException If mapping fails
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
     * Get mapping from all registered profiles.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param string $property Property name
     * @return PropertyMapping|null Property mapping or null if not found
     */
    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        // Check direct mappings first
        $mapping = $this->getMappingFromStorage($sourceType, $destinationType, $property);
        if ($mapping !== null) {
            return $mapping;
        }

        // Then check profiles
        foreach ($this->profiles as $profile) {
            $mapping = $profile->getMapping($sourceType, $destinationType, $property);
            if ($mapping !== null) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Get mapping from direct storage.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param string $property Property name
     * @return PropertyMapping|null Property mapping or null if not found
     */
    private function getMappingFromStorage(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        $key = $sourceType . '->' . $destinationType;
        return $this->mappings[$key][$property] ?? null;
    }
    /**
     * Get all mappings for a type pair from all sources.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array<string, PropertyMapping> Property mappings indexed by property name
     */
    public function getMappingsForTypes(string $sourceType, string $destinationType): array
    {
        $key = $sourceType . '->' . $destinationType;
        $result = $this->mappings[$key] ?? [];

        // Merge mappings from profiles
        foreach ($this->profiles as $profile) {
            $profileMappings = $profile->getMappingsForTypes($sourceType, $destinationType);
            foreach ($profileMappings as $property => $mapping) {
                if (!isset($result[$property])) {
                    $result[$property] = $mapping;
                }
            }
        }

        return $result;
    }

    /**
     * Normalize source data to array format.
     *
     * @param mixed $source Source data
     * @return array Normalized data as array
     * @throws MappingException If source type is not supported
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
     *
     * @param object $source Source object
     * @return array Object data as array
     * @throws MappingException If reflection fails
     */
    private function objectToArray(object $source): array
    {
        try {
            // Special handling for stdClass objects
            if ($source instanceof \stdClass) {
                return (array) $source;
            }
            
            $result = [];
            $properties = ReflectionCache::getPublicProperties(get_class($source));

            foreach ($properties as $property) {
                if ($property->isInitialized($source)) {
                    $result[$property->getName()] = $property->getValue($source);
                }
            }

            return $result;
        } catch (Exception $e) {
            throw MappingException::unsupportedSourceType($source);
        }
    }

    /**
     * Get mapping configuration for source to destination.
     *
     * @param mixed $source Source data
     * @param string $destinationType Destination type name
     * @return array Mapping configuration
     */
    private function getMappingConfiguration(mixed $source, string $destinationType): array
    {
        $sourceType = is_object($source) ? get_class($source) : 'array';

        // Check cache first
        if ($this->cache->has($sourceType, $destinationType)) {
            return $this->cache->get($sourceType, $destinationType);
        }

        // Build and cache if not found
        $config = $this->buildMappingConfiguration($sourceType, $destinationType);
        $this->cache->put($sourceType, $destinationType, $config);

        return $config;
    }

    /**
     * Build mapping configuration.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array Mapping configuration
     */
    private function buildMappingConfiguration(string $sourceType, string $destinationType): array
    {
        $config = [];

        try {
            $destinationProperties = ReflectionCache::getPublicProperties($destinationType);

            foreach ($destinationProperties as $property) {
                $propertyName = $property->getName();
                $mappingInfo = $this->getPropertyMappingInfo($property);

                if ($mappingInfo['ignore']) {
                    continue;
                }

                // Get profile mapping first
                $profileMapping = $this->getMapping($sourceType, $destinationType, $propertyName);

                // Determine source property
                $source = $mappingInfo['source'] ?? $propertyName;

                // If profile mapping has a source defined, use that instead
                if ($profileMapping !== null && $profileMapping->getSourceProperty() !== null) {
                    $source = $profileMapping->getSourceProperty();
                }

                // Set up the transformer
                $transformer = $mappingInfo['transformer'] ?? null;

                // If this is a collection, create a collection transformer
                if ($mappingInfo['isCollection'] && $mappingInfo['collectionConfig'] !== null) {
                    $collectionConfig = $mappingInfo['collectionConfig'];
                    $transformer = $collectionConfig->createTransformer($this);
                }

                $config[$propertyName] = [
                    'source' => $source,
                    'transformer' => $transformer,
                    'profile_mapping' => $profileMapping,
                    'condition' => $mappingInfo['condition'] ?? null,
                    'defaultValue' => $mappingInfo['defaultValue'] ?? null,
                    'hasDefaultValue' => $mappingInfo['hasDefaultValue'] ?? false
                ];
            }

            return $config;
        } catch (Exception $e) {
            // Return empty config if something fails
            return [];
        }
    }

    /**
     * Get property mapping info from attributes.
     *
     * @param ReflectionProperty $property Property reflection
     * @return array Property mapping info
     */
    private function getPropertyMappingInfo(ReflectionProperty $property): array
    {
        $info = [
            'ignore' => false,
            'source' => null,
            'transformer' => null,
            'condition' => null,
            'defaultValue' => null,
            'hasDefaultValue' => false,
            'isCollection' => false,
            'collectionConfig' => null,
            'bidirectional' => null
        ];

        $attributes = $property->getAttributes();

        foreach ($attributes as $attribute) {
            try {
                $instance = $attribute->newInstance();

                if ($instance instanceof Ignore) {
                    $info['ignore'] = true;
                } elseif ($instance instanceof MapFrom) {
                    $info['source'] = $instance->source;
                } elseif ($instance instanceof MapWith) {
                    $info['transformer'] = $instance->transformer;
                } elseif ($instance instanceof MapWhen) {
                    $info['condition'] = $instance->condition;
                } elseif ($instance instanceof MapDefault) {
                    $info['defaultValue'] = $instance->value;
                    $info['hasDefaultValue'] = true;
                } elseif ($instance instanceof MapCollection) {
                    $info['isCollection'] = true;
                    $info['collectionConfig'] = $instance;
                } elseif ($instance instanceof MapBidirectional) {
                    $info['bidirectional'] = $instance->otherProperty;
                }
            } catch (Exception $e) {
                // Skip attributes that can't be instantiated
                continue;
            }
        }

        return $info;
    }

    /**
     * Apply mappings to source data.
     *
     * @param array $sourceData Source data
     * @param array $mappingConfig Mapping configuration
     * @return array Transformed data
     */
    private function applyMappings(array $sourceData, array $mappingConfig): array
    {
        $result = [];

        foreach ($mappingConfig as $destinationProperty => $config) {
            $sourceKey = $config['source'];
            $transformer = $config['transformer'];
            $profileMapping = $config['profile_mapping'];
            $condition = $config['condition'] ?? null;
            $defaultValue = $config['defaultValue'] ?? null;
            $hasDefaultValue = $config['hasDefaultValue'] ?? false;

            // Check condition if set
            if ($condition !== null) {
                if (is_callable($condition)) {
                    if (!$condition($sourceData)) {
                        if ($hasDefaultValue) {
                            $result[$destinationProperty] = $defaultValue;
                        }
                        continue;
                    }
                } elseif (is_string($condition) && method_exists($destinationProperty, $condition)) {
                    if (!$destinationProperty::$condition($sourceData)) {
                        if ($hasDefaultValue) {
                            $result[$destinationProperty] = $defaultValue;
                        }
                        continue;
                    }
                }
            }

            // Get source value
            $sourceValue = $this->getSourceValue($sourceData, $sourceKey);

            // Apply transformations
            if ($profileMapping !== null) {
                // If there's a profile mapping, use that to transform
                $transformedValue = $profileMapping->transform($sourceValue, $sourceData);
                $result[$destinationProperty] = $transformedValue;
            } elseif ($transformer !== null) {
                // If there's a direct transformer
                $transformedValue = $this->applyTransformer($sourceValue, $transformer, $sourceData);
                $result[$destinationProperty] = $transformedValue;
            } else {
                // No transformation
                $result[$destinationProperty] = $sourceValue;
            }

            // Apply default value if value is null and default is set
            if ($result[$destinationProperty] === null && $hasDefaultValue) {
                $result[$destinationProperty] = $defaultValue;
            }
        }

        return $result;
    }

    /**
     * Get source value using dot notation support.
     *
     * @param array $sourceData Source data
     * @param string $key Property key
     * @return mixed Property value
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
     *
     * @param array $data Source data
     * @param string $key Nested property key (using dot notation)
     * @return mixed Property value
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
     *
     * @param mixed $value Value to transform
     * @param mixed $transformer Transformer to apply
     * @param array $sourceData Source data for context
     * @return mixed Transformed value
     */
    private function applyTransformer(mixed $value, mixed $transformer, array $sourceData = []): mixed
    {
        if (is_callable($transformer)) {
            return $transformer($value, $sourceData);
        }

        if ($transformer instanceof Transformer) {
            return $transformer->transform($value, $sourceData);
        }

        return $value;
    }

    /**
     * Create an object from array data.
     *
     * @param array $data Object data
     * @param string $className Class name
     * @return object Created object
     * @throws MappingException|\Ninja\Granite\Exceptions\ReflectionException If object creation fails
     */
    private function createObjectFromArray(array $data, string $className): object
    {
        try {
            $reflection = ReflectionCache::getClass($className);
            
            // Create instance using constructor to respect default values
            $constructor = $reflection->getConstructor();
            
            if ($constructor) {
                // Get constructor parameters
                $parameters = $constructor->getParameters();
                $args = [];
                
                // Prepare constructor arguments with values from mapped data
                foreach ($parameters as $param) {
                    $paramName = $param->getName();
                    if (array_key_exists($paramName, $data)) {
                        $args[] = $data[$paramName];
                        // Remove from data so we don't set it twice
                        unset($data[$paramName]);
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } elseif ($param->allowsNull()) {
                        $args[] = null;
                    } else {
                        // If parameter is required and has no value, use a type-appropriate default
                        $type = $param->getType();
                        if ($type && !$type->allowsNull()) {
                            $typeName = $type->getName();
                            switch ($typeName) {
                                case 'int':
                                    $args[] = 0;
                                    break;
                                case 'float':
                                    $args[] = 0.0;
                                    break;
                                case 'bool':
                                    $args[] = false;
                                    break;
                                case 'string':
                                    $args[] = '';
                                    break;
                                case 'array':
                                    $args[] = [];
                                    break;
                                default:
                                    // For other types, we'll have to use null and hope for the best
                                    $args[] = null;
                            }
                        } else {
                            $args[] = null;
                        }
                    }
                }
                
                // Create instance with constructor
                $instance = $reflection->newInstanceArgs($args);
            } else {
                // Fallback to creating without constructor if no constructor exists
                $instance = $reflection->newInstanceWithoutConstructor();
            }
            
            // Set any remaining properties that weren't handled by the constructor
            return $this->populateObject($instance, $data);
        } catch (ReflectionException $e) {
            throw new MappingException('array', $className, "Failed to create instance: " . $e->getMessage());
        }
    }

    /**
     * Populate an existing object with data.
     *
     * @param object $object Object to populate
     * @param array $data Data to set
     * @return object Populated object
     * @throws MappingException If population fails
     */
    private function populateObject(object $object, array $data): object
    {
        try {
            $properties = ReflectionCache::getPublicProperties(get_class($object));

            foreach ($properties as $property) {
                $propertyName = $property->getName();
                if (array_key_exists($propertyName, $data)) {
                    $property->setValue($object, $data[$propertyName]);
                }
            }

            return $object;
        } catch (Exception $e) {
            throw new MappingException('array', get_class($object), "Failed to populate object: " . $e->getMessage());
        }
    }

    /**
     * Create mappings from type configurations with bidirectional support.
     *
     * @param array $typeConfigs Type configurations
     * @return void
     * @throws MappingException
     */
    public function processBidirectionalMappings(array $typeConfigs): void
    {
        // First pass: identify bidirectional mappings
        $bidirectionalPairs = [];

        foreach ($typeConfigs as $sourceType => $destTypeConfigs) {
            foreach ($destTypeConfigs as $destType => $propertyConfigs) {
                foreach ($propertyConfigs as $destProp => $config) {
                    if (isset($config['bidirectional'])) {
                        $bidirectionalPairs[$sourceType][$destType][$destProp] = $config['bidirectional'];
                    }
                }
            }
        }

        // Second pass: create bidirectional mappings
        foreach ($bidirectionalPairs as $typeA => $typeBMappings) {
            foreach ($typeBMappings as $typeB => $propMappings) {
                $biMapping = $this->createMapBidirectional($typeA, $typeB);

                foreach ($propMappings as $propA => $propB) {
                    $biMapping->forMembers($propA, $propB);
                }

                $biMapping->seal();
            }
        }
    }

    /**
     * Clear mapping cache.
     *
     * @return $this For method chaining
     */
    public function clearCache(): self
    {
        $this->cache->clear();
        return $this;
    }

    /**
     * Set cache warming.
     *
     * @param bool $enabled Whether to enable cache warming
     * @return $this For method chaining
     */
    public function setCacheWarming(bool $enabled): self
    {
        $this->warmupCache = $enabled;

        if ($enabled && !empty($this->profiles)) {
            $this->warmupCache();
        }

        return $this;
    }

    /**
     * Get the current cache.
     *
     * @return MappingCache Current cache
     */
    public function getCache(): MappingCache
    {
        return $this->cache;
    }

    /**
     * Set a different cache.
     *
     * @param MappingCache $cache New cache
     * @return $this For method chaining
     */
    public function setCache(MappingCache $cache): self
    {
        $this->cache = $cache;

        if ($this->warmupCache && !empty($this->profiles)) {
            $this->warmupCache();
        }

        return $this;
    }
}
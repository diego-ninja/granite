<?php

namespace Ninja\Granite\Traits;

use DateMalformedStringException;
use InvalidArgumentException;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionException;
use ReflectionProperty;
use ReflectionType;

/**
 * Trait providing deserialization functionality for Granite objects.
 * Handles creation of objects from arrays, JSON, and other data sources.
 */
trait HasDeserialization
{
    /**
     * Get the class-level naming convention if defined.
     * This method will be provided by HasNamingConventions trait.
     *
     * @param string $class Class name
     * @return NamingConvention|null The naming convention or null if not defined
     */
    abstract protected static function getClassConvention(string $class): ?NamingConvention;

    /**
     * Get the class-level DateTime provider if defined.
     * This method will be provided by HasCarbonSupport trait.
     *
     * @param string $class Class name
     * @return DateTimeProvider|null The DateTime provider or null if not defined
     */
    abstract protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider;

    /**
     * Find value in data using multiple lookup strategies.
     * This method will be provided by HasNamingConventions trait.
     *
     * @param array $data Input data
     * @param string $phpName PHP property name
     * @param string $serializedName Configured serialized name
     * @param NamingConvention|null $convention Class convention
     * @return mixed Found value or null
     */
    abstract protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed;

    /**
     * Convert value to the specified type.
     * This method will be provided by HasTypeConversion trait.
     *
     * @param mixed $value The value to convert
     * @param ReflectionType|null $type The target type
     * @param ReflectionProperty|null $property The property being converted (for attribute access)
     * @param DateTimeProvider|null $classProvider Class-level DateTime provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     */
    abstract protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed;
    /**
     * Create a new instance from various data sources.
     *
     * Supports multiple invocation patterns transparently:
     * - from(['key' => 'value']) - Array data
     * - from('{"key": "value"}') - JSON string
     * - from($graniteObject) - Another Granite object
     * - from(key: 'value', anotherKey: 'anotherValue') - Named parameters
     * - from($baseData, key: 'override') - Base data with named parameter overrides
     *
     * @param mixed ...$args Variable arguments supporting all patterns
     * @return static New instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    public static function from(mixed ...$args): static
    {
        if (empty($args)) {
            // Create empty instance with uninitialized properties
            $instance = self::createEmptyInstance();
            /** @var static $result */
            $result = $instance;
            return $result;
        }

        // Check if args has string keys (named parameters)
        $hasStringKeys = ! empty(array_filter(array_keys($args), 'is_string'));

        if ($hasStringKeys) {
            // Named parameters detected (pure or mixed)
            $baseData = [];
            $namedOverrides = [];

            foreach ($args as $key => $value) {
                if (is_numeric($key)) {
                    // Positional argument - should be structured data
                    if (self::looksLikeStructuredData($value) &&
                        (is_array($value) || is_string($value) || $value instanceof GraniteObject)) {
                        $normalized = self::normalizeInputData($value);
                        $baseData = array_merge($baseData, $normalized);
                    }
                } else {
                    // Named parameter - use as override
                    $namedOverrides[$key] = $value;
                }
            }

            // Merge base data with named overrides (named take precedence)
            $data = array_merge($baseData, $namedOverrides);
        } else {
            // Regular positional arguments - resolve them
            $data = self::resolveArgumentsToData($args);
        }

        static::validateData($data, static::class);

        // Check if we need to use constructor due to readonly properties from parent classes
        if (self::hasReadonlyPropertiesFromParentClasses()) {
            return self::createInstanceWithConstructor($data);
        }

        $instance = self::createEmptyInstance();
        return self::hydrateInstance($instance, $data);
    }

    /**
     * Resolve function arguments to normalized data array.
     * Handles automatic detection of named parameters vs regular arguments.
     * @throws Exceptions\ReflectionException
     */
    protected static function resolveArgumentsToData(array $args): array
    {
        if (1 === count($args)) {
            $firstArg = $args[0];

            // Single argument - check if it looks like structured data
            if (self::looksLikeStructuredData($firstArg) &&
                (is_array($firstArg) || is_string($firstArg) || $firstArg instanceof GraniteObject)) {
                return self::normalizeInputData($firstArg);
            }

            // Single scalar argument - treat as property value for first property
            return self::mapScalarToFirstProperty($firstArg);
        }

        // Multiple arguments - check if they are all scalar values (likely named parameters)
        $allScalar = true;
        foreach ($args as $arg) {
            if (self::looksLikeStructuredData($arg)) {
                $allScalar = false;
                break;
            }
        }

        if ($allScalar) {
            // All scalar values - treat as named parameters mapped by position
            return self::buildFromPositionalArgs($args);
        }

        // Mixed arguments - check if first argument looks like structured data
        $baseData = [];
        $startIndex = 0;

        if ( ! empty($args) && self::looksLikeStructuredData($args[0]) &&
            (is_array($args[0]) || is_string($args[0]) || $args[0] instanceof GraniteObject)) {
            $baseData = self::normalizeInputData($args[0]);
            $startIndex = 1;
        }

        // Map remaining arguments to properties by position
        $namedData = self::buildFromPositionalArgs(array_slice($args, $startIndex));

        return array_merge($baseData, $namedData);
    }

    /**
     * Map a single scalar value to the first property of the class.
     * @throws Exceptions\ReflectionException
     */
    protected static function mapScalarToFirstProperty(mixed $value): array
    {
        $properties = ReflectionCache::getPublicProperties(static::class);
        if (empty($properties)) {
            return [];
        }

        $firstProperty = reset($properties);
        return [$firstProperty->getName() => $value];
    }


    /**
     * Check if a value looks like structured data (array, JSON, or GraniteObject).
     * This helps distinguish between scalar values and actual data sources.
     */
    protected static function looksLikeStructuredData(mixed $value): bool
    {
        // Arrays and GraniteObjects are clearly structured data
        if (is_array($value) || $value instanceof GraniteObject) {
            return true;
        }

        // For strings, check if it looks like JSON
        if (is_string($value)) {
            // Quick check: JSON strings typically start with { or [
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                // If it looks like JSON, treat it as structured data regardless of validity
                // Let normalizeInputData handle the validation and throw appropriate exceptions
                return true;
            }
        }

        return false;
    }

    /**
     * Build data array from positional arguments.
     * Maps arguments to class properties by order.
     *
     * @param array $args All arguments passed to from()
     * @return array Associative array with property names as keys
     * @throws Exceptions\ReflectionException
     */
    protected static function buildFromPositionalArgs(array $args): array
    {
        $properties = ReflectionCache::getPublicProperties(static::class);
        $result = [];

        foreach ($properties as $index => $property) {
            if ( ! isset($args[$index])) {
                break;
            }

            $propertyName = $property->getName();
            $result[$propertyName] = $args[$index];
        }

        return $result;
    }

    /**
     * Helper method for child classes to implement named parameter support.
     * Child classes can override from() with their specific parameter signature
     * and use this method to create instances.
     *
     * Example usage in child class:
     * public static function from(
     *     array|string|GraniteObject $data = [],
     *     ?string $name = null,
     *     ?int $age = null,
     *     ?string $email = null
     * ): static {
     *     return self::fromNamedParameters(get_defined_vars());
     * }
     *
     * @param array<string, mixed> $namedParams Associative array of parameter name => value
     * @return static New instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    protected static function fromNamedParameters(array $namedParams): static
    {
        // If 'data' parameter is provided and not empty, use it as primary source
        if ( ! empty($namedParams['data']) &&
            (is_array($namedParams['data']) || is_string($namedParams['data']) ||
             $namedParams['data'] instanceof GraniteObject)) {
            $data = self::normalizeInputData($namedParams['data']);
            // Merge with other named parameters (named params take precedence)
            unset($namedParams['data']);
            $data = array_merge($data, array_filter($namedParams, fn($v) => null !== $v));
        } else {
            // Use named parameters directly, filtering out null values
            $data = array_filter($namedParams, fn($v) => null !== $v);
            unset($data['data']); // Remove the data parameter if it was null/empty
        }

        static::validateData($data, static::class);

        $instance = self::createEmptyInstance();
        return self::hydrateInstance($instance, $data);
    }

    /**
     * Normalize input data to array format.
     *
     * @param array|string|GraniteObject $data Input data
     * @return array Normalized data
     */
    protected static function normalizeInputData(array|string|GraniteObject $data): array
    {
        if ($data instanceof GraniteObject) {
            return $data->array();
        }

        if (is_string($data)) {
            if (json_validate($data)) {
                $decoded = json_decode($data, true);
                return is_array($decoded) ? $decoded : [];
            }
            throw new InvalidArgumentException('Invalid JSON string provided');

        }

        // If we reach here, $data must be an array due to union type
        return $data;
    }

    /**
     * Create empty instance without constructor.
     *
     * @throws Exceptions\ReflectionException
     */
    protected static function createEmptyInstance(): object
    {
        try {
            $reflection = ReflectionCache::getClass(static::class);
            return $reflection->newInstanceWithoutConstructor();
        } catch (ReflectionException $e) {
            throw Exceptions\ReflectionException::classNotFound(static::class);
        }
    }

    /**
     * @param object $instance Instance to hydrate
     * @param array $data Data to hydrate with
     * @return static Hydrated instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    protected static function hydrateInstance(object $instance, array $data): static
    {
        $properties = ReflectionCache::getPublicProperties(static::class);

        // Get serialization metadata and class convention
        $metadata = MetadataCache::getMetadata(static::class);
        $classConvention = self::getClassConvention(static::class);
        $classDateTimeProvider = self::getClassDateTimeProvider(static::class);

        // Process each property
        foreach ($properties as $property) {
            $phpName = $property->getName();
            $serializedName = $metadata->getSerializedName($phpName);

            // Try to find the value in the input data with multiple strategies
            $value = self::findValueInData($data, $phpName, $serializedName, $classConvention);

            // Skip if property is not found in data
            if (null === $value && ! array_key_exists($phpName, $data) &&
                ! array_key_exists($serializedName, $data)) {
                continue;
            }

            $type = $property->getType();
            $convertedValue = self::convertValueToType($value, $type, $property, $classDateTimeProvider);
            
            // In PHP 8.3, readonly properties can only be set by the declaring class
            // Check if this is a readonly property from a parent class
            if ($property->isReadOnly() && $property->getDeclaringClass()->getName() !== static::class) {
                // Skip readonly properties from parent classes in PHP 8.3
                // They should be initialized through the parent constructor
                continue;
            }
            
            $property->setValue($instance, $convertedValue);
        }

        /** @var static $result */
        $result = $instance;
        return $result;
    }

    /**
     * Check if the class has readonly properties from parent classes.
     * This helps determine if we need to use constructor initialization.
     *
     * @return bool True if readonly properties from parent classes exist
     * @throws Exceptions\ReflectionException
     */
    protected static function hasReadonlyPropertiesFromParentClasses(): bool
    {
        $properties = ReflectionCache::getPublicProperties(static::class);

        foreach ($properties as $property) {
            if ($property->isReadOnly() && $property->getDeclaringClass()->getName() !== static::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create instance using constructor for readonly property compatibility.
     * This method maps data to constructor parameters.
     *
     * @param array $data Data to use for initialization
     * @return static Created instance
     * @throws Exceptions\ReflectionException
     */
    protected static function createInstanceWithConstructor(array $data): static
    {
        try {
            $reflection = ReflectionCache::getClass(static::class);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                // No constructor, fall back to empty instance + hydration
                $instance = self::createEmptyInstance();
                return self::hydrateInstance($instance, $data);
            }

            $parameters = $constructor->getParameters();
            $args = [];

            // Map data to constructor parameters
            foreach ($parameters as $param) {
                $paramName = $param->getName();
                
                if (array_key_exists($paramName, $data)) {
                    $args[] = $data[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $args[] = null;
                } else {
                    // Required parameter not found in data, use null and let constructor handle it
                    $args[] = null;
                }
            }

            $instance = $reflection->newInstanceArgs($args);
            
            // Hydrate any remaining properties not handled by constructor
            return self::hydrateRemainingProperties($instance, $data);

        } catch (ReflectionException $e) {
            throw Exceptions\ReflectionException::classNotFound(static::class);
        }
    }

    /**
     * Hydrate properties not handled by constructor.
     *
     * @param object $instance Instance to hydrate
     * @param array $data Data to hydrate with
     * @return static Hydrated instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    protected static function hydrateRemainingProperties(object $instance, array $data): static
    {
        $properties = ReflectionCache::getPublicProperties(static::class);
        $reflection = ReflectionCache::getClass(static::class);
        $constructor = $reflection->getConstructor();
        $constructorParams = $constructor ? array_map(fn($p) => $p->getName(), $constructor->getParameters()) : [];

        // Get serialization metadata and class convention
        $metadata = MetadataCache::getMetadata(static::class);
        $classConvention = self::getClassConvention(static::class);
        $classDateTimeProvider = self::getClassDateTimeProvider(static::class);

        // Process properties not handled by constructor
        foreach ($properties as $property) {
            $phpName = $property->getName();
            
            // Skip if this property was handled by constructor
            if (in_array($phpName, $constructorParams, true)) {
                continue;
            }

            // Skip readonly properties from parent classes (they should be set by constructor)
            if ($property->isReadOnly() && $property->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            $serializedName = $metadata->getSerializedName($phpName);

            // Try to find the value in the input data with multiple strategies
            $value = self::findValueInData($data, $phpName, $serializedName, $classConvention);

            // Skip if property is not found in data
            if (null === $value && ! array_key_exists($phpName, $data) &&
                ! array_key_exists($serializedName, $data)) {
                continue;
            }

            $type = $property->getType();
            $convertedValue = self::convertValueToType($value, $type, $property, $classDateTimeProvider);
            $property->setValue($instance, $convertedValue);
        }

        /** @var static $result */
        $result = $instance;
        return $result;
    }
}

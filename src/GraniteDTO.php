<?php

namespace Ninja\Granite;

use BackedEnum;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionAttribute;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use UnitEnum;

abstract readonly class GraniteDTO implements GraniteObject
{
    /**
     * @param string|array|GraniteObject $data Source data
     * @return static New instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    public static function from(string|array|GraniteObject $data): static
    {
        $data = self::normalizeInputData($data);

        // Check if class is readonly and has constructor
        $reflection = ReflectionCache::getClass(static::class);
        if ($reflection->isReadOnly() && $reflection->getConstructor()) {
            return self::createReadonlyInstance($data);
        }

        $instance = self::createEmptyInstance();

        /** @var static $result */
        $result = self::hydrateInstance($instance, $data);
        return $result;
    }

    /**
     * @return array Serialized array
     * @throws RuntimeException If a property cannot be serialized
     * @throws ReflectionException
     * @throws SerializationException
     */
    public function array(): array
    {
        $result = [];
        $properties = ReflectionCache::getPublicProperties(static::class);
        $metadata = MetadataCache::getMetadata(static::class);

        foreach ($properties as $property) {
            $phpName = $property->getName();

            // Skip hidden properties
            if ($metadata->isHidden($phpName)) {
                continue;
            }

            // Skip uninitialized properties
            if ( ! $property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);
            $serializedValue = $this->serializeValue($phpName, $value);

            // Use custom property name if defined (includes convention-applied names)
            $serializedName = $metadata->getSerializedName($phpName);
            $result[$serializedName] = $serializedValue;
        }

        return $result;
    }

    /**
     * @throws ReflectionException
     */
    public function json(): string
    {
        $json = json_encode($this->array());
        if (false === $json) {
            throw new RuntimeException('Failed to encode object to JSON');
        }
        return $json;
    }

    protected static function normalizeInputData(string|array|GraniteObject $data): array
    {
        if ($data instanceof GraniteObject) {
            return $data->array();
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if ( ! is_array($decoded)) {
                throw new InvalidArgumentException('Invalid JSON string provided');
            }
            return $decoded;
        }

        return $data;
    }

    /**
     * Define custom property names for serialization.
     * Override in child classes to customize property names.
     *
     * @return array<string, string> Mapping of PHP property names to serialized names
     */
    protected static function serializedNames(): array
    {
        return [];
    }

    /**
     * Define properties that should be hidden during serialization.
     * Override in child classes to hide specific properties.
     *
     * @return array<string> List of property names to hide
     */
    protected static function hiddenProperties(): array
    {
        return [];
    }

    /**
     * Creates instance for readonly classes using constructor.
     *
     * @param array $data Source data
     * @return static New readonly instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    private static function createReadonlyInstance(array $data): static
    {
        try {
            $reflection = ReflectionCache::getClass(static::class);
            $constructor = $reflection->getConstructor();

            if ( ! $constructor) {
                throw new RuntimeException('Readonly class ' . static::class . ' must have a constructor');
            }

            $parameters = $constructor->getParameters();
            $args = [];

            // Get serialization metadata and class convention
            $metadata = MetadataCache::getMetadata(static::class);
            $classConvention = self::getClassConvention(static::class);

            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();
                $serializedName = $metadata->getSerializedName($paramName);

                // Find value using same strategy as hydrateInstance
                $value = self::findValueInData($data, $paramName, $serializedName, $classConvention);

                if (null === $value && ! array_key_exists($paramName, $data) && ! array_key_exists($serializedName, $data)) {
                    // If parameter is not present in data, fall back to reflection approach
                    // This allows properties to remain uninitialized regardless of default values
                    return self::fallbackToReflectionApproach($data);
                }
                $convertedValue = self::convertValueToType($value, $parameter->getType());
                $args[] = $convertedValue;

            }

            /** @var static $instance */
            $instance = $reflection->newInstanceArgs($args);
            return $instance;

        } catch (ReflectionException $e) {
            throw Exceptions\ReflectionException::classNotFound(static::class);
        }
    }

    /**
     * Fallback method for readonly classes when constructor approach fails.
     *
     * @param array $data Source data
     * @return static New instance using reflection
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    private static function fallbackToReflectionApproach(array $data): static
    {
        $instance = self::createEmptyInstance();

        /** @var static $result */
        $result = self::hydrateInstance($instance, $data);
        return $result;
    }

    /**
     * @throws Exceptions\ReflectionException
     */
    private static function createEmptyInstance(): object
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
    private static function hydrateInstance(object $instance, array $data): static
    {
        $properties = ReflectionCache::getPublicProperties(static::class);

        // Get serialization metadata and class convention
        $metadata = MetadataCache::getMetadata(static::class);
        $classConvention = self::getClassConvention(static::class);

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
            $convertedValue = self::convertValueToType($value, $type);
            $property->setValue($instance, $convertedValue);
        }

        /** @var static $result */
        $result = $instance;
        return $result;
    }

    /**
     * Find value in data using multiple lookup strategies.
     *
     * @param array $data Input data
     * @param string $phpName PHP property name
     * @param string $serializedName Configured serialized name
     * @param NamingConvention|null $convention Class convention
     * @return mixed Found value or null
     */
    private static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        // Strategy 1: Direct PHP name match
        if (array_key_exists($phpName, $data)) {
            return $data[$phpName];
        }

        // Strategy 2: Configured serialized name match
        if ($phpName !== $serializedName && array_key_exists($serializedName, $data)) {
            return $data[$serializedName];
        }

        // Strategy 3: Convention-based lookup (bidirectional)
        if (null !== $convention) {
            // Try to find a key that would convert to our PHP property name via the convention
            foreach (array_keys($data) as $key) {
                if (self::conventionMatches($key, $phpName, $convention)) {
                    return $data[$key];
                }
            }
        }

        return null;
    }

    /**
     * Check if a data key matches the PHP property name via convention.
     *
     * @param string $dataKey Key from input data
     * @param string $phpName PHP property name
     * @param NamingConvention $convention Naming convention
     * @return bool Whether they match
     */
    private static function conventionMatches(string $dataKey, string $phpName, NamingConvention $convention): bool
    {
        try {
            // Normalize both names and compare
            $dataKeyNormalized = $dataKey;
            $phpNameNormalized = $phpName;

            // If dataKey matches the convention, normalize it
            if ($convention->matches($dataKey)) {
                $dataKeyNormalized = $convention->normalize($dataKey);
            }

            // If phpName matches a different convention, we need to normalize it too
            // For now, assume phpNames are in camelCase and normalize via convention
            if ($convention->matches($phpName)) {
                $phpNameNormalized = $convention->normalize($phpName);
            } else {
                // Try to convert phpName to the normalized form
                // This is a simplified approach - in practice you might want more sophisticated detection
                $converted = preg_replace('/([a-z])([A-Z])/', '$1 $2', $phpName);
                $phpNameNormalized = mb_strtolower($converted ?? $phpName);
            }

            return $dataKeyNormalized === $phpNameNormalized;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the class-level naming convention if defined.
     *
     * @param class-string $class Class name
     * @return NamingConvention|null The naming convention or null if not defined
     */
    private static function getClassConvention(string $class): ?NamingConvention
    {
        try {
            $reflection = ReflectionCache::getClass($class);
            $conventionAttrs = $reflection->getAttributes(SerializationConvention::class, ReflectionAttribute::IS_INSTANCEOF);

            if (empty($conventionAttrs)) {
                return null;
            }

            $conventionAttr = $conventionAttrs[0]->newInstance();

            // Only return if bidirectional is enabled
            return $conventionAttr->bidirectional ? $conventionAttr->getConvention() : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param mixed $value The value to convert
     * @param ReflectionType|null $type The target type
     * @return mixed Converted value
     * @throws DateMalformedStringException
     */
    private static function convertValueToType(mixed $value, ?ReflectionType $type): mixed
    {
        if (null === $value) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            return self::convertToNamedType($value, $type);
        }

        if ($type instanceof ReflectionUnionType) {
            return self::convertToUnionType($value, $type);
        }

        return $value;
    }

    /**
     * @param mixed $value The value to convert
     * @param ReflectionNamedType $type The target type
     * @return mixed Converted value
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private static function convertToNamedType(mixed $value, ReflectionNamedType $type): mixed
    {
        $typeName = $type->getName();

        // Check for GraniteObject first
        if (is_subclass_of($typeName, GraniteObject::class)) {
            if (null === $value) {
                return null;
            }
            if (is_array($value) || is_string($value) || $value instanceof GraniteObject) {
                return $typeName::from($value);
            }
            return null;
        }

        // Check for DateTime
        if (DateTimeInterface::class === $typeName || is_subclass_of($typeName, DateTimeInterface::class)) {
            if ($value instanceof DateTimeInterface) {
                return $value;
            }

            if (is_string($value)) {
                return new DateTimeImmutable($value);
            }
            return null;
        }

        // Check for Enum (PHP 8.1+)
        if (interface_exists('UnitEnum') && is_subclass_of($typeName, UnitEnum::class)) {
            // If value is already the correct enum instance
            if ($value instanceof $typeName) {
                return $value;
            }

            // Try to convert string/int to an enum case
            if (is_string($value) || is_int($value)) {
                // For BackedEnum (with values)
                if (is_subclass_of($typeName, BackedEnum::class)) {
                    return $typeName::tryFrom($value);
                }

                // For UnitEnum (without values)
                foreach ($typeName::cases() as $case) {
                    if ($case->name === $value) {
                        return $case;
                    }
                }
            }

            // If we couldn't convert to an enum, return null
            return null;
        }

        return $value;
    }

    /**
     * @param mixed $value The value to convert
     * @param ReflectionUnionType $type The union type
     * @return mixed Converted value
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private static function convertToUnionType(mixed $value, ReflectionUnionType $type): mixed
    {
        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType) {
                $typeName = $unionType->getName();
                if (DateTimeInterface::class === $typeName || is_subclass_of($typeName, DateTimeInterface::class)) {
                    if (is_string($value)) {
                        return new DateTimeImmutable($value);
                    }
                    return $value;
                }
            }
        }

        return $value;
    }

    /**
     * @param string $propertyName The property name (for error reporting)
     * @param mixed $value The value to serialize
     * @return mixed Serialized value
     * @throws SerializationException If the value cannot be serialized
     */
    private function serializeValue(string $propertyName, mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        if (is_scalar($value) || is_array($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (interface_exists('UnitEnum') && $value instanceof UnitEnum) {
            if ($value instanceof BackedEnum) {
                return $value->value;
            }
            return $value->name;
        }

        if ($value instanceof GraniteObject) {
            return $value->array();
        }

        throw SerializationException::unsupportedType(static::class, $propertyName, get_debug_type($value));
    }
}

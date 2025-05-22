<?php

namespace Ninja\Granite;

use BackedEnum;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Support\ReflectionCache;
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
     * @throws ReflectionException
     * @throws Exceptions\ReflectionException
     */
    public static function from(string|array|GraniteObject $data): static
    {
        $data = self::normalizeInputData($data);
        $instance = self::createEmptyInstance();

        return self::hydrateInstance($instance, $data);
    }

    protected static function normalizeInputData(string|array|GraniteObject $data): array
    {
        if ($data instanceof GraniteObject) {
            return $data->array();
        }

        if (is_string($data)) {
            return json_decode($data, true);
        }

        return $data;
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
     * @param GraniteObject $instance Instance to hydrate
     * @param array $data Data to hydrate with
     * @return static Hydrated instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    private static function hydrateInstance(GraniteObject $instance, array $data): GraniteObject
    {
        $properties = ReflectionCache::getPublicProperties(static::class);

        // Get serialization metadata
        $metadata = MetadataCache::getMetadata(static::class);

        // Process each property
        foreach ($properties as $property) {
            $phpName = $property->getName();
            $serializedName = $metadata->getSerializedName($phpName);

            // Try to find the value in the input data - could be under PHP name or serialized name
            $value = null;
            $found = false;

            if (array_key_exists($phpName, $data)) {
                $value = $data[$phpName];
                $found = true;
            } elseif ($phpName !== $serializedName && array_key_exists($serializedName, $data)) {
                $value = $data[$serializedName];
                $found = true;
            }

            // Skip if property is not in data
            if (!$found) {
                continue;
            }

            $type = $property->getType();
            $convertedValue = self::convertValueToType($value, $type);
            $property->setValue($instance, $convertedValue);
        }

        return $instance;
    }

    /**
     * @param mixed $value The value to convert
     * @param ReflectionType|null $type The target type
     * @return mixed Converted value
     * @throws DateMalformedStringException
     */
    private static function convertValueToType(mixed $value, ?ReflectionType $type): mixed
    {
        if ($value === null) {
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
            return $value ? $typeName::from($value) : null;
        }

        // Check for DateTime
        if ($typeName === DateTimeInterface::class || is_subclass_of($typeName, DateTimeInterface::class)) {
            return $value ? new DateTimeImmutable($value) : null;
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
                    return $typeName::from($value);
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
            $typeName = $unionType->getName();
            if ($typeName === DateTimeInterface::class || is_subclass_of($typeName, DateTimeInterface::class)) {
                return $value ? new DateTimeImmutable($value) : null;
            }
        }

        return $value;
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
            if (!$property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);
            $serializedValue = $this->serializeValue($phpName, $value);

            // Use custom property name if defined
            $serializedName = $metadata->getSerializedName($phpName);
            $result[$serializedName] = $serializedValue;
        }

        return $result;
    }

    /**
     * @param string $propertyName The property name (for error reporting)
     * @param mixed $value The value to serialize
     * @return mixed Serialized value
     * @throws SerializationException If the value cannot be serialized
     */
    private function serializeValue(string $propertyName, mixed $value): mixed
    {
        if ($value === null) {
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

    /**
     * @throws ReflectionException
     */
    public function json(): string
    {
        return json_encode($this->array());
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
}
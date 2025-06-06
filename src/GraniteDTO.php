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
     * @throws Exceptions\ReflectionException
     */
    public static function from(string|array|GraniteObject $data): static
    {
        $data = self::normalizeInputData($data);
        $reflectionClass = ReflectionCache::getClass(static::class);
        $constructor = $reflectionClass->getConstructor(); // Use cached reflection

        $propertyCount = count($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE));
        if (!$constructor && $reflectionClass->isReadOnly() && $propertyCount > 0) {
            // A readonly class with properties should have an implicit constructor if none is defined.
            // This check is to see if we're unexpectedly getting no constructor for readonly DTOs.
            throw Exceptions\ReflectionException::noImplicitConstructorFound(static::class . " (Actual Property Count: {$propertyCount})");
        }

        $constructorArgs = [];
        $remainingData = $data; // Data not used in constructor
        $metadata = MetadataCache::getMetadata(static::class); // Needed for serializedName lookup for constructor params

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                $paramType = $param->getType();
                $serializedName = $metadata->getSerializedName($paramName);
                $dataKeyUsed = null;

                if (array_key_exists($paramName, $data)) {
                    $dataKeyUsed = $paramName;
                } elseif ($paramName !== $serializedName && array_key_exists($serializedName, $data)) {
                    $dataKeyUsed = $serializedName;
                }

                if ($dataKeyUsed !== null) {
                    $value = self::convertValueToType($data[$dataKeyUsed], $paramType);
                    $constructorArgs[$param->getPosition()] = $value;
                    unset($remainingData[$dataKeyUsed]);
                } elseif ($param->isDefaultValueAvailable()) {
                    $constructorArgs[$param->getPosition()] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $constructorArgs[$param->getPosition()] = null;
                } else {
                    $typeName = $paramType instanceof ReflectionNamedType ? $paramType->getName() : (string) $paramType;
                    if (is_subclass_of($typeName, GraniteObject::class) || $typeName === GraniteObject::class) {
                        throw new Exceptions\ReflectionException(
                            "Missing required constructor parameter '{$paramName}' of type '{$typeName}' for class " . static::class . ". No data provided and no default value available."
                        );
                    }
                    $constructorArgs[$param->getPosition()] = match ($typeName) {
                        'int' => 0,
                        'float' => 0.0,
                        'string' => '',
                        'bool' => false,
                        'array' => [],
                        default => null,
                    };
                }
            }
        }

        $instance = $reflectionClass->newInstanceArgs($constructorArgs);

        // Hydrate remaining public, non-readonly properties
        self::hydratePublicWritableProperties($instance, $remainingData);

        return $instance;
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
     * @param GraniteObject $instance Instance to hydrate
     * @param array $data Data to hydrate with
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    private static function hydratePublicWritableProperties(GraniteObject $instance, array $data): void
    {
        $properties = ReflectionCache::getPublicProperties(static::class);
        $metadata = MetadataCache::getMetadata(static::class);

        foreach ($properties as $property) {
            $phpName = $property->getName();

            // Skip readonly properties as they should be set by constructor
            if ($property->isReadOnly()) {
                continue;
            }

            $serializedName = $metadata->getSerializedName($phpName); // For name mapping
            $valueToSet = null; // Renamed $value to $valueToSet for clarity
            $found = false;

            if (array_key_exists($phpName, $data)) { // Prefer direct PHP name
                $valueToSet = $data[$phpName];
                $found = true;
            } elseif ($phpName !== $serializedName && array_key_exists($serializedName, $data)) { // Fallback to serialized name
                $valueToSet = $data[$serializedName];
                $found = true;
            }

            // Skip if property is not found in the remaining data
            if (!$found) {
                continue;
            }

            $type = $property->getType();
            // Use $valueToSet instead of $value
            $convertedValue = self::convertValueToType($valueToSet, $type);
            $property->setValue($instance, $convertedValue);
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
            if ($value instanceof DateTimeInterface) {
                return $value;
            }

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
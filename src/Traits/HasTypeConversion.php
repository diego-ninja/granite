<?php

namespace Ninja\Granite\Traits;

use BackedEnum;
use DateMalformedStringException;
use DateTimeInterface;
use Exception;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Support\CarbonSupport;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * Trait providing type conversion functionality for Granite objects.
 * Handles conversion between different data types during deserialization.
 */
trait HasTypeConversion
{
    /**
     * Convert value to Carbon instance.
     * This method will be provided by HasCarbonSupport trait.
     *
     * @param mixed $value Value to convert
     * @param string $typeName Target Carbon type name
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return DateTimeInterface|null Carbon instance
     */
    abstract protected static function convertToCarbon(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface;

    /**
     * Convert value to DateTime instance (with possible Carbon auto-conversion).
     * This method will be provided by HasCarbonSupport trait.
     *
     * @param mixed $value Value to convert
     * @param string $typeName Target DateTime type name
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return DateTimeInterface|null DateTime instance
     * @throws DateMalformedStringException
     */
    abstract protected static function convertToDateTime(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface;
    /**
     * @param mixed $value The value to convert
     * @param ReflectionType|null $type The target type
     * @param ReflectionProperty|null $property The property being converted (for attribute access)
     * @param DateTimeProvider|null $classProvider Class-level DateTime provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     */
    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        if (null === $value) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            return self::convertToNamedType($value, $type, $property, $classProvider);
        }

        if ($type instanceof ReflectionUnionType) {
            return self::convertToUnionType($value, $type, $property, $classProvider);
        }

        return $value;
    }

    /**
     * @param mixed $value The value to convert
     * @param ReflectionNamedType $type The target type
     * @param ReflectionProperty|null $property The property being converted
     * @param DateTimeProvider|null $classProvider Class-level DateTime provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private static function convertToNamedType(
        mixed $value,
        ReflectionNamedType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        $typeName = $type->getName();

        // Check for Carbon classes first (before general DateTime check)
        if (CarbonSupport::isCarbonClass($typeName)) {
            return self::convertToCarbon($value, $typeName, $property, $classProvider);
        }

        // Check for GraniteObject first
        if (is_subclass_of($typeName, GraniteObject::class)) {
            if (null === $value) {
                return null;
            }

            return $typeName::from($value);
        }

        // Check for DateTime
        if (DateTimeInterface::class === $typeName || is_subclass_of($typeName, DateTimeInterface::class)) {
            return self::convertToDateTime($value, $typeName, $property, $classProvider);
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
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private static function convertToUnionType(
        mixed $value,
        ReflectionUnionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType) {
                $typeName = $unionType->getName();

                // Try Carbon types first
                if (CarbonSupport::isCarbonClass($typeName)) {
                    $result = self::convertToCarbon($value, $typeName, $property, $classProvider);
                    if (null !== $result) {
                        return $result;
                    }
                }

                // Try DateTime types
                if (DateTimeInterface::class === $typeName || is_subclass_of($typeName, DateTimeInterface::class)) {
                    $result = self::convertToDateTime($value, $typeName, $property, $classProvider);
                    if (null !== $result) {
                        return $result;
                    }
                }
            }
        }

        return $value;
    }
}

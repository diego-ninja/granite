<?php

namespace Ninja\Granite\Exceptions;

/**
 * Exception thrown when object comparison fails.
 */
class ComparisonException extends GraniteException
{
    /**
     * Create exception for type mismatch during comparison.
     */
    public static function typeMismatch(string $expectedType, string $actualType): self
    {
        return new self(
            sprintf(
                'Cannot compare objects of different types: expected %s, got %s',
                $expectedType,
                $actualType,
            ),
            context: [
                'expected_type' => $expectedType,
                'actual_type' => $actualType,
            ],
        );
    }

    /**
     * Create exception for uncomparable values.
     */
    public static function uncomparableValue(string $propertyName, mixed $value): self
    {
        return new self(
            sprintf(
                'Property "%s" contains uncomparable value of type "%s"',
                $propertyName,
                get_debug_type($value),
            ),
            context: [
                'property_name' => $propertyName,
                'value_type' => get_debug_type($value),
            ],
        );
    }
}

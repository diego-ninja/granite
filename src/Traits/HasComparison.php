<?php
// ABOUTME: Defines HasComparison as part of reusable Granite object behavior.
// ABOUTME: Owns the HasComparison boundary within reusable Granite object behavior.

namespace Ninja\Granite\Traits;

use BackedEnum;
use DateTimeInterface;
use Ninja\Granite\Exceptions\ComparisonException;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Granite;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Support\ValueComparator;
use UnitEnum;

trait HasComparison
{
    /**
     * Check if this DTO is equal to another DTO of the same type.
     *
     * Only compares initialized public properties.
     *
     * @param Granite $other Another Granite object to compare against
     * @return bool True if all properties are equal
     * @throws ReflectionException
     */
    public function equals(Granite $other): bool
    {
        // Early return if not same class
        if ( ! $other instanceof static) {
            return false;
        }

        $profile = ReflectionCache::getClassProfile(static::class);
        if ($profile->canUseFastPath) {
            return $profile->areEqual($this, $other);
        }

        $properties = ReflectionCache::getPublicProperties(static::class);

        foreach ($properties as $property) {
            $thisInitialized = $property->isInitialized($this);
            $otherInitialized = $property->isInitialized($other);

            if ($thisInitialized !== $otherInitialized) {
                return false;
            }

            if ( ! $thisInitialized) {
                continue;
            }

            $currentValue = $property->getValue($this);
            $otherValue = $property->getValue($other);

            if ( ! $this->valuesAreEqual($currentValue, $otherValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get differences between this object and another.
     *
     * Returns an array of properties that differ, with their current and new values.
     * Nested Granite objects are recursively compared.
     *
     * @param Granite $other Another Granite object to compare against
     * @return array<string, mixed> Array of differences
     * @throws ComparisonException If comparison fails for incomparable types
     * @throws ReflectionException
     * @throws SerializationException
     */
    public function differs(Granite $other): array
    {
        if ( ! $other instanceof static) {
            throw ComparisonException::typeMismatch(static::class, $other::class);
        }

        $differences = [];
        $properties = ReflectionCache::getPublicProperties(static::class);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            $thisInitialized = $property->isInitialized($this);
            $otherInitialized = $property->isInitialized($other);

            if ($thisInitialized !== $otherInitialized) {
                $differences[$propertyName] = [
                    'current' => $thisInitialized ? $this->valueToComparable($property->getValue($this)) : self::uninitializedValue(),
                    'new' => $otherInitialized ? $this->valueToComparable($property->getValue($other)) : self::uninitializedValue(),
                ];
                continue;
            }

            if ( ! $thisInitialized) {
                continue;
            }

            $currentValue = $property->getValue($this);
            $otherValue = $property->getValue($other);

            if ( ! $this->valuesAreEqual($currentValue, $otherValue)) {
                // Handle nested Granite objects
                if ($currentValue instanceof Granite && $otherValue instanceof Granite) {
                    try {
                        $nestedDifferences = $currentValue->differs($otherValue);
                        if ( ! empty($nestedDifferences)) {
                            $differences[$propertyName] = $nestedDifferences;
                        }
                    } catch (ComparisonException $e) {
                        // If nested comparison fails, treat as different values
                        $differences[$propertyName] = [
                            'current' => $this->valueToComparable($currentValue),
                            'new' => $this->valueToComparable($otherValue),
                        ];
                    }
                } else {
                    $differences[$propertyName] = [
                        'current' => $this->valueToComparable($currentValue),
                        'new' => $this->valueToComparable($otherValue),
                    ];
                }
            }
        }

        return $differences;
    }

    /** @return array{__uninitialized: true} */
    private static function uninitializedValue(): array
    {
        return ['__uninitialized' => true];
    }

    /**
     * Check if two values are equal.
     *
     * Handles null, scalars, arrays, objects, enums, DateTimes, and Granite objects.
     *
     * @param mixed $value1 First value
     * @param mixed $value2 Second value
     * @return bool True if values are equal
     * @throws ReflectionException
     */
    private function valuesAreEqual(mixed $value1, mixed $value2): bool
    {
        return ValueComparator::equals($value1, $value2);
    }

    /**
     * Convert a value to a comparable representation for diff output.
     *
     * @param mixed $value Value to convert
     * @return mixed Comparable representation
     * @throws ReflectionException
     * @throws SerializationException
     */
    private function valueToComparable(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u P'); // Include microseconds and timezone
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof Granite) {
            return $value->array();
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->valueToComparable($v), $value);
        }

        // For other objects, try to get a meaningful representation
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            // Last resort: class name + property dump
            return [
                '__class' => $value::class,
                '__string' => get_debug_type($value),
            ];
        }

        return get_debug_type($value);
    }
}

<?php

namespace Ninja\Granite\Traits;

use BackedEnum;
use DateTimeInterface;
use Ninja\Granite\Exceptions\ComparisonException;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Granite;
use Ninja\Granite\Support\ReflectionCache;
use UnitEnum;

trait HasComparation
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

        $properties = ReflectionCache::getPublicProperties(static::class);

        foreach ($properties as $property) {
            // Skip uninitialized properties
            if ( ! $property->isInitialized($this) || ! $property->isInitialized($other)) {
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

            // Skip uninitialized properties
            if ( ! $property->isInitialized($this) || ! $property->isInitialized($other)) {
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
        // Handle null cases
        if (null === $value1 && null === $value2) {
            return true;
        }

        if (null === $value1 || null === $value2) {
            return false;
        }

        // Handle Granite objects recursively
        if ($value1 instanceof Granite && $value2 instanceof Granite) {
            return $value1->equals($value2);
        }

        // Handle DateTimeInterface
        if ($value1 instanceof DateTimeInterface && $value2 instanceof DateTimeInterface) {
            return $value1->getTimestamp() === $value2->getTimestamp()
                && $value1->getTimezone()->getName() === $value2->getTimezone()->getName();
        }

        // Handle arrays recursively
        if (is_array($value1) && is_array($value2)) {
            return $this->arraysAreEqual($value1, $value2);
        }

        // Handle enums
        if ($value1 instanceof UnitEnum && $value2 instanceof UnitEnum) {
            if ($value1 instanceof BackedEnum && $value2 instanceof BackedEnum) {
                return $value1->value === $value2->value;
            }
            return $value1->name === $value2->name;
        }

        // Handle scalar values (string, int, float, bool)
        if (is_scalar($value1) && is_scalar($value2)) {
            return $value1 === $value2;
        }

        // Handle other objects - try __toString or compare class + serialization
        if (is_object($value1) && is_object($value2)) {
            // Different classes are always different
            if ($value1::class !== $value2::class) {
                return false;
            }

            // If both have __toString, compare string representation
            if (method_exists($value1, '__toString') && method_exists($value2, '__toString')) {
                return (string) $value1 === (string) $value2;
            }

            // Last resort: serialize and compare (slower but accurate)
            return serialize($value1) === serialize($value2);
        }

        // Different types are never equal
        return false;
    }

    /**
     * Deep comparison of arrays.
     *
     * @param array $array1 First array
     * @param array $array2 Second array
     * @return bool True if arrays are equal
     * @throws ReflectionException
     */
    private function arraysAreEqual(array $array1, array $array2): bool
    {
        // Different sizes mean different arrays
        if (count($array1) !== count($array2)) {
            return false;
        }

        // Compare each key-value pair
        foreach ($array1 as $key => $value) {
            if ( ! array_key_exists($key, $array2)) {
                return false;
            }

            if ( ! $this->valuesAreEqual($value, $array2[$key])) {
                return false;
            }
        }

        return true;
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

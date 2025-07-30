<?php

namespace Ninja\Granite;

use DateMalformedStringException;
use InvalidArgumentException;
use Ninja\Granite\Exceptions\SerializationException;

abstract readonly class GraniteVO extends Granite
{
    /**
     * Create a new Value Object instance with validation.
     *
     * @param mixed ...$args Variable arguments supporting multiple patterns
     * @return static The new Value Object instance
     * @throws InvalidArgumentException If validation fails
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    public static function from(mixed ...$args): static
    {
        if (empty($args)) {
            throw new InvalidArgumentException('At least one argument is required');
        }

        return parent::from(...$args);
    }

    /**
     * Compare this Value Object with another Value Object or array.
     * Two Value Objects are equal if they have the same class and the same property values.
     *
     * @param mixed $other The Value Object or array to compare with
     * @return bool True if equal, false otherwise
     * @throws Exceptions\ReflectionException
     * @throws SerializationException
     */
    public function equals(mixed $other): bool
    {
        // If comparing with the same instance
        if ($this === $other) {
            return true;
        }

        // If comparing with another Value Object
        if ($other instanceof self) {
            // Must be the same class
            if (get_class($this) !== get_class($other)) {
                return false;
            }

            // Convert both to arrays and compare
            $thisArray = $this->array();
            $otherArray = $other->array();

            return $thisArray === $otherArray; // Using loose comparison for array values
        }

        // If comparing with an array
        if (is_array($other)) {
            $thisArray = $this->array();

            // Check if the array has all the properties
            foreach ($thisArray as $key => $value) {
                if ( ! array_key_exists($key, $other) || $other[$key] !== $value) {
                    return false;
                }
            }

            // Check if the array has extra properties
            foreach ($other as $key => $value) {
                if ( ! array_key_exists($key, $thisArray)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Create a new instance with some properties modified.
     * This respects immutability by creating a new instance.
     *
     * @param array $modifications Properties to modify
     * @return static New Value Object with modifications
     * @throws InvalidArgumentException If validation fails
     * @throws DateMalformedStringException
     * @throws SerializationException|Exceptions\ReflectionException
     */
    public function with(array $modifications): static
    {
        // Start with the current values
        $data = $this->array();

        // Apply modifications
        foreach ($modifications as $property => $value) {
            $data[$property] = $value;
        }

        // Create new instance with modified data
        return static::from($data);
    }
}

<?php
// ABOUTME: Defines GraniteVO as part of the Granite runtime.
// ABOUTME: Owns the GraniteVO boundary within the Granite runtime.

namespace Ninja\Granite;

use DateMalformedStringException;
use InvalidArgumentException;
use Ninja\Granite\Exceptions\SerializationException;

/**
 * @deprecated Use Granite instead. This class will be removed in v3.0.0
 * @see Granite
 */
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
        if ($other instanceof Granite) {
            return parent::equals($other);
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
     * @param array<array-key, mixed> $modifications Properties to modify
     * @return static New Value Object with modifications
     * @throws InvalidArgumentException If validation fails
     * @throws DateMalformedStringException
     * @throws SerializationException|Exceptions\ReflectionException
     */
    public function with(array $modifications): static
    {
        return parent::with($modifications);
    }
}

<?php

namespace Ninja\Granite;

use DateMalformedStringException;
use InvalidArgumentException;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Validation\GraniteValidator;
use Ninja\Granite\Validation\RuleExtractor;
use ReflectionException;

abstract readonly class GraniteVO extends GraniteDTO
{
    /**
     * Create a new Value Object instance with validation.
     *
     * @param string|array|GraniteObject $data Source data
     * @return static The new Value Object instance
     * @throws InvalidArgumentException If validation fails
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public static function from(string|array|GraniteObject $data): static
    {
        // First normalize the data to work with an array
        $data = self::normalizeInputData($data);

        // Get rules from both method and attributes
        $methodRules = static::rules();
        $attributeRules = RuleExtractor::extractRules(static::class);

        // Merge rules, preferring method rules if defined for the same property
        $rules = $attributeRules;
        foreach ($methodRules as $property => $propertyRules) {
            $rules[$property] = $propertyRules;
        }

        // Create validator and validate data
        GraniteValidator::fromArray($rules)->validate($data, static::class);

        // Create the instance using parent method
        return parent::from($data);
    }

    /**
     * Get validation rules for this Value Object.
     * Override this method in child classes to define validation rules.
     *
     * @return array<string, array> Validation rules by property name
     */
    protected static function rules(): array
    {
        return [];
    }

    /**
     * Compare this Value Object with another Value Object or array.
     * Two Value Objects are equal if they have the same class and the same property values.
     *
     * @param mixed $other The Value Object or array to compare with
     * @return bool True if equal, false otherwise
     * @throws ReflectionException
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

            return $thisArray == $otherArray; // Using loose comparison for array values
        }

        // If comparing with an array
        if (is_array($other)) {
            $thisArray = $this->array();

            // Check if the array has all the properties
            foreach ($thisArray as $key => $value) {
                if (!array_key_exists($key, $other) || $other[$key] != $value) {
                    return false;
                }
            }

            // Check if the array has extra properties
            foreach ($other as $key => $value) {
                if (!array_key_exists($key, $thisArray)) {
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
     * @throws ReflectionException
     * @throws DateMalformedStringException
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
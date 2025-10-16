<?php

namespace Ninja\Granite;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Ninja\Granite\Exceptions\SerializationException;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Pebble - A lightweight, immutable data container for quick object snapshots.
 *
 * Pebble provides a simple way to create readonly, immutable versions of mutable objects
 * without the overhead of validation, custom serialization, or type definitions.
 *
 * Use cases:
 * - Quick snapshots of Eloquent models or other mutable objects
 * - Lightweight DTOs for simple data transfer
 * - Immutable copies for caching or comparison
 *
 * For advanced features like validation, custom serialization, or type safety,
 * use the full Granite class instead.
 *
 * Example:
 * ```php
 * $user = User::query()->first(); // Eloquent model (mutable)
 * $userSnapshot = Pebble::from($user); // Immutable snapshot
 *
 * echo $userSnapshot->name;  // Access via magic __get
 * echo $userSnapshot->email;
 *
 * $json = $userSnapshot->json();
 * $array = $userSnapshot->array();
 * ```
 *
 * @since 2.1.0
 */
final readonly class Pebble implements JsonSerializable, ArrayAccess, Countable
{
    /**
     * Internal data storage.
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * Cached fingerprint for fast comparisons.
     * Computed eagerly at construction.
     */
    private string $fingerprint;

    /**
     * Private constructor to enforce usage of static factory methods.
     *
     * @param array<string, mixed> $data Extracted data
     */
    private function __construct(array $data)
    {
        $this->data = $data;

        // Compute fingerprint eagerly since we can't modify after construction (readonly)
        $serialized = serialize($this->data);

        if (function_exists('hash') && in_array('xxh3', hash_algos(), true)) {
            $this->fingerprint = hash('xxh3', $serialized);
        } elseif (function_exists('hash') && in_array('xxh64', hash_algos(), true)) {
            $this->fingerprint = hash('xxh64', $serialized);
        } else {
            $this->fingerprint = md5($serialized);
        }
    }

    /**
     * Get a property value.
     *
     * @param string $name Property name
     * @return mixed Property value or null if not found
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Check if a property exists.
     *
     * @param string $name Property name
     * @return bool True if property exists
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Prevent setting properties (immutability).
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @throws InvalidArgumentException Always throws, as Pebble is immutable
     */
    public function __set(string $name, mixed $value): void
    {
        throw new InvalidArgumentException(
            'Cannot modify Pebble properties. Pebble objects are immutable. ' .
            'Create a new instance with Pebble::from() instead.',
        );
    }

    /**
     * Prevent unsetting properties (immutability).
     *
     * @param string $name Property name
     * @throws InvalidArgumentException Always throws, as Pebble is immutable
     */
    public function __unset(string $name): void
    {
        throw new InvalidArgumentException(
            'Cannot unset Pebble properties. Pebble objects are immutable.',
        );
    }

    /**
     * Convert to string representation.
     *
     * @return string JSON representation
     */
    public function __toString(): string
    {
        try {
            return $this->json();
        } catch (Throwable) {
            return '{}';
        }
    }

    /**
     * Debug info for var_dump.
     *
     * @return array<string, mixed> Data for debugging
     */
    public function __debugInfo(): array
    {
        return $this->data;
    }

    /**
     * Create an immutable Pebble from various data sources.
     *
     * Supports:
     * - Arrays: ['key' => 'value']
     * - Objects: Eloquent models, stdClass, any object with public properties
     * - JSON strings: '{"key": "value"}'
     * - Granite objects: User::from([...])
     *
     * @param array<string, mixed>|object|string $source Data source
     * @return self Immutable Pebble instance
     * @throws Exceptions\ReflectionException
     * @throws SerializationException
     */
    public static function from(array|object|string $source): self
    {
        return new self(self::extractData($source));
    }

    /**
     * Get all data as an array.
     *
     * @return array<string, mixed> Data array
     */
    public function array(): array
    {
        return $this->data;
    }

    /**
     * Convert to JSON string.
     *
     * @return string JSON representation
     * @throws JsonException
     */
    public function json(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR);
    }

    /**
     * JsonSerializable implementation.
     *
     * @return array<string, mixed> Data for JSON encoding
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * Get the fingerprint (hash) of this Pebble's data.
     * Used for fast equality comparisons.
     *
     * @return string 128-bit xxh3 hash as hexadecimal
     */
    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Compare this Pebble with another Pebble or array.
     * Uses fingerprint comparison for Pebble-to-Pebble comparisons for O(1) performance.
     *
     * @param mixed $other Comparison target
     * @return bool True if equal
     */
    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if ($other instanceof self) {
            // Fast O(1) fingerprint comparison
            return $this->fingerprint() === $other->fingerprint();
        }

        if (is_array($other)) {
            // For arrays, compute hash on the fly
            $serialized = serialize($other);

            if (function_exists('hash') && in_array('xxh3', hash_algos(), true)) {
                $otherHash = hash('xxh3', $serialized);
            } elseif (function_exists('hash') && in_array('xxh64', hash_algos(), true)) {
                $otherHash = hash('xxh64', $serialized);
            } else {
                $otherHash = md5($serialized);
            }

            return $this->fingerprint() === $otherHash;
        }

        return false;
    }

    /**
     * Check if Pebble is empty.
     *
     * @return bool True if no data
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Get the number of properties.
     * Implements Countable interface, allowing count($pebble).
     *
     * @return int Property count
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Check if a property exists with the given name.
     *
     * @param string $name Property name
     * @return bool True if property exists
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Get a property with a default value if not found.
     *
     * @param string $name Property name
     * @param mixed $default Default value
     * @return mixed Property value or default
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    /**
     * Get only specified properties.
     *
     * @param array<string> $keys Property names to extract
     * @return self New Pebble with only specified properties
     */
    public function only(array $keys): self
    {
        $filtered = array_intersect_key(
            $this->data,
            array_flip($keys),
        );
        return new self($filtered);
    }

    /**
     * Get all properties except specified ones.
     *
     * @param array<string> $keys Property names to exclude
     * @return self New Pebble without specified properties
     */
    public function except(array $keys): self
    {
        $filtered = array_diff_key(
            $this->data,
            array_flip($keys),
        );
        return new self($filtered);
    }

    /**
     * Create a new Pebble with merged data.
     *
     * @param array<string, mixed> $data Additional data to merge
     * @return self New Pebble with merged data
     */
    public function merge(array $data): self
    {
        return new self(array_merge($this->data, $data));
    }

    /**
     * ArrayAccess: Check if offset exists.
     *
     * @param mixed $offset Property name
     * @return bool True if property exists
     */
    public function offsetExists(mixed $offset): bool
    {
        if (! is_int($offset) && ! is_string($offset)) {
            return false;
        }

        return array_key_exists($offset, $this->data);
    }

    /**
     * ArrayAccess: Get value at offset.
     *
     * @param mixed $offset Property name
     * @return mixed Property value or null if not found
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * ArrayAccess: Set value at offset (disabled for immutability).
     *
     * @param mixed $offset Property name
     * @param mixed $value Property value
     * @throws InvalidArgumentException Always throws, as Pebble is immutable
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new InvalidArgumentException(
            'Cannot modify Pebble properties via array access. Pebble objects are immutable. ' .
            'Create a new instance with Pebble::from() or use merge() instead.',
        );
    }

    /**
     * ArrayAccess: Unset value at offset (disabled for immutability).
     *
     * @param mixed $offset Property name
     * @throws InvalidArgumentException Always throws, as Pebble is immutable
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new InvalidArgumentException(
            'Cannot unset Pebble properties via array access. Pebble objects are immutable.',
        );
    }

    /**
     * Extract data from various source types.
     *
     * @param array<string, mixed>|object|string $source Data source
     * @return array<string, mixed> Extracted data
     * @throws Exceptions\ReflectionException
     * @throws SerializationException
     */
    private static function extractData(array|object|string $source): array
    {
        // Array source
        if (is_array($source)) {
            /** @var array<string, mixed> $source */
            return $source;
        }

        // JSON string source
        if (is_string($source)) {
            if ( ! json_validate($source)) {
                throw new InvalidArgumentException('Invalid JSON string provided to Pebble::from()');
            }

            $decoded = json_decode($source, true);
            if ( ! is_array($decoded)) {
                throw new InvalidArgumentException('JSON string must decode to an associative array');
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        // Object source - try multiple extraction strategies
        return self::extractFromObject($source);
    }

    /**
     * Extract data from an object using various strategies.
     *
     * Priority order:
     * 1. Granite objects - use array() method
     * 2. Objects with toArray() method
     * 3. Objects implementing JsonSerializable
     * 4. Public properties extraction
     * 5. Getter methods (getName() -> name)
     *
     * @param object $source Source object
     * @return array<string, mixed> Extracted data
     * @throws Exceptions\ReflectionException
     * @throws SerializationException
     */
    private static function extractFromObject(object $source): array
    {
        // Strategy 1: Granite objects
        if ($source instanceof Granite) {
            /** @var array<string, mixed> $graniteArray */
            $graniteArray = $source->array();
            return $graniteArray;
        }

        // Strategy 2: toArray() method
        if (method_exists($source, 'toArray')) {
            /** @var mixed $result */
            $result = $source->toArray();
            /** @var array<string, mixed> $arrayResult */
            $arrayResult = is_array($result) ? $result : [];
            return $arrayResult;
        }

        // Strategy 3: JsonSerializable
        if ($source instanceof JsonSerializable) {
            /** @var mixed $result */
            $result = $source->jsonSerialize();
            /** @var array<string, mixed> $arrayResult */
            $arrayResult = is_array($result) ? $result : [];
            return $arrayResult;
        }

        // Strategy 4: Public properties
        $data = self::extractPublicProperties($source);

        // Strategy 5: Enrich with getter methods
        /** @var array<string, mixed> $result */
        $result = array_merge($data, self::extractGetters($source, $data));
        return $result;
    }

    /**
     * Extract public properties from an object.
     *
     * @param object $source Source object
     * @return array<string, mixed> Public properties
     */
    private static function extractPublicProperties(object $source): array
    {
        // Use get_object_vars for performance (faster than reflection for public props)
        /** @var array<string, mixed> $vars */
        $vars = get_object_vars($source);
        return $vars;
    }

    /**
     * Extract data from getter methods.
     *
     * Converts methods like getName() to 'name' property.
     * Only extracts getters that don't already exist in the data.
     *
     * @param object $source Source object
     * @param array<string, mixed> $existingData Existing extracted data
     * @return array<string, mixed> Data from getters
     */
    private static function extractGetters(object $source, array $existingData): array
    {
        $getterData = [];
        $methods = get_class_methods($source);

        if ([] === $methods) {
            return $getterData;
        }

        foreach ($methods as $method) {
            // Match getter methods: getName, getEmail, isActive, hasPermission
            if ( ! preg_match('/^(get|is|has)([A-Z].*)$/', $method, $matches)) {
                continue;
            }

            // Convert to property name: getName -> name, isActive -> active
            $propertyName = lcfirst($matches[2]);

            // Skip if already exists in data
            if (array_key_exists($propertyName, $existingData)) {
                continue;
            }

            // Call getter and store value
            try {
                $reflection = new ReflectionMethod($source, $method);

                // Skip if method requires parameters
                if ($reflection->getNumberOfRequiredParameters() > 0) {
                    continue;
                }

                // Skip if not public
                if ( ! $reflection->isPublic()) {
                    continue;
                }

                $getterData[$propertyName] = $source->{$method}();
            } catch (ReflectionException) {
                // Skip this getter if reflection fails
                continue;
            }
        }

        return $getterData;
    }
}

<?php

namespace Ninja\Granite;

use ArrayAccess;
use Countable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Ninja\Granite\Exceptions\SerializationException;
use ReflectionException;
use ReflectionMethod;
use Throwable;
use UnitEnum;

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
     * @throws JsonException
     */
    private function __construct(array $data)
    {
        $this->data = $data;

        // Compute fingerprint eagerly since we can't modify after construction (readonly)
        $this->fingerprint = self::computeFingerprint($this->data);
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
            'Cannot modify Pebble properties. Pebble objects are immutable. '
            . 'Create a new instance with Pebble::from() instead.',
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
     * Create a new immutable Pebble from an array, object, or JSON string.
     *
     * Supports arrays, objects (including Granite objects, objects with public properties,
     * objects exposing toArray()/jsonSerialize(), and objects with public no-arg getters),
     * and JSON-encoded strings.
     *
     * @param array<string, mixed>|object|string $source Source data to convert into a Pebble
     * @return self A new immutable Pebble containing the extracted data
     * @throws Exceptions\ReflectionException If reflection fails while extracting getters
     * @throws SerializationException If an object cannot be serialized during extraction
     * @throws JsonException If JSON decoding or encoding fails during extraction or fingerprinting
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
         * Determine whether this Pebble's data equals another Pebble or an array.
         *
         * Compares two Pebbles by their precomputed fingerprints for constant-time comparison.
         * When given an array, the array is canonicalized and fingerprinted using the same
         * normalization rules before comparison.
         *
         * @param mixed $other A Pebble or an associative array to compare against.
         * @return bool `true` if the underlying data are equal, `false` otherwise.
         * @throws JsonException If normalization or fingerprinting of an array fails.
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
            // For arrays, compute hash on the fly using the same canonicalization
            /** @phpstan-ignore-next-line */
            return $this->fingerprint() === self::computeFingerprint($other);
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
         * Create a new Pebble containing only the specified keys.
         *
         * Missing keys are ignored; only keys present in the current Pebble will appear
         * in the resulting Pebble.
         *
         * @param array<string> $keys Property names to extract
         * @return self A new Pebble containing only the specified properties
         * @throws JsonException
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
     * Create a new Pebble containing all properties except the specified keys.
     *
     * @param array<string> $keys Property names to exclude from the resulting Pebble.
     * @return self A new Pebble instance that excludes the given properties.
     * @throws JsonException If serialization or fingerprint computation fails during construction.
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
         * Create a new Pebble containing this Pebble's data merged with the provided array.
         *
         * @param array<string, mixed> $data Additional data to merge; values in this array overwrite existing keys.
         * @return self A new Pebble whose data is the result of merging the original data with `$data`.
         * @throws JsonException If the merged data cannot be serialized when computing the fingerprint.
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
        if ( ! is_int($offset) && ! is_string($offset)) {
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
        if ( ! is_string($offset) && ! is_int($offset)) {
            return null;
        }
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
            'Cannot modify Pebble properties via array access. Pebble objects are immutable. '
            . 'Create a new instance with Pebble::from() or use merge() instead.',
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
     * Produce an associative array of property names to values extracted from an object.
     *
     * Extraction follows this priority: Granite objects via array(), objects with toArray(), JsonSerializable objects via jsonSerialize(), public properties, then public no-argument getters (getX/isX/hasX) to populate missing keys.
     *
     * @param object $source Source object to extract data from
     * @return array<string, mixed> Associative array of extracted data keyed by property name
     * @throws Exceptions\ReflectionException If reflection inspection of getters fails
     * @throws SerializationException If serialization-related operations fail during extraction
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
     * Extracts public no-argument getter values from an object and returns them as an associative array.
     *
     * Only methods matching getX(), isX(), or hasX() are considered; each is converted to a lower-cased property name (e.g., getName() -> name).
     * Getters already present in $existingData are skipped. Methods that are non-public, require parameters, throw during invocation, or cannot be reflected are ignored.
     *
     * @param object $source Source object to extract getters from.
     * @param array<string, mixed> $existingData Previously extracted data used to avoid overwriting existing keys.
     * @return array<string, mixed> Associative array of property names to values extracted from getters.
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

                // Invoke the getter - may throw runtime exceptions
                try {
                    $getterData[$propertyName] = $source->{$method}();
                } catch (Throwable $e) {
                    // Skip this getter if it throws any exception during invocation
                    // This prevents a single failing getter from aborting the entire snapshot
                    continue;
                }
            } catch (ReflectionException) {
                // Skip this getter if reflection fails
                continue;
            }
        }

        return $getterData;
    }

    /**
     * Produce a stable fingerprint for the given data array by normalizing it and hashing its JSON representation.
     *
     * @param array<string, mixed> $data The data to normalize and hash; normalization ensures stable ordering for associative arrays and consistent representation for DateTimeInterface, JsonSerializable, enums, objects, and resources.
     * @return string The resulting hash fingerprint.
     * @throws JsonException If the data cannot be JSON-encoded.
     * @internal
     */
    private static function computeFingerprint(array $data): string
    {
        $normalized = self::normalizeForHash($data);
        $json = json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        $algos = hash_algos();
        $algo = in_array('xxh128', $algos, true)
            ? 'xxh128'
            : (in_array('xxh3', $algos, true) ? 'xxh3' : 'sha256');

        return hash($algo, $json);
    }

    /**
         * Produce a stable, comparable representation of a value suitable for fingerprinting.
         *
         * Converts/normalizes supported types and recursively normalizes arrays so that semantically
         * identical values produce the same structure regardless of insertion order for associative arrays.
         *
         * @internal
         * @param mixed $value Value to normalize for hashing
         * @return mixed Normalized value (scalars unchanged; arrays with associative keys sorted; JsonSerializable converted via jsonSerialize; DateTimeInterface to DATE_ATOM string; UnitEnum to its `value` or `name`; objects to their public properties; resources replaced by their type)
         */
    private static function normalizeForHash(mixed $value): mixed
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                ksort($value);
            }
            foreach ($value as $k => $v) {
                $value[$k] = self::normalizeForHash($v);
            }
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            return self::normalizeForHash($value->jsonSerialize());
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof UnitEnum) {
            /** @phpstan-ignore-next-line */
            return property_exists($value, 'value') ? $value->value : $value->name;
        }

        if (is_object($value)) {
            return self::normalizeForHash(get_object_vars($value));
        }

        if (is_resource($value)) {
            return get_resource_type($value);
        }

        return $value;
    }
}
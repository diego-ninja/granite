<?php
// ABOUTME: Defines HasNamingConventions as part of reusable Granite object behavior.
// ABOUTME: Owns the HasNamingConventions boundary within reusable Granite object behavior.

namespace Ninja\Granite\Traits;

use Exception;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionAttribute;

/**
 * Trait providing naming convention functionality for Granite objects.
 * Handles property name mapping and lookup strategies during serialization/deserialization.
 */
trait HasNamingConventions
{
    /**
     * Find value in data using multiple lookup strategies.
     *
     * @param array<array-key, mixed> $data Input data
     * @param string $phpName PHP property name
     * @param string $serializedName Configured serialized name
     * @param NamingConvention|null $convention Class convention
     * @return mixed Found value or null
     */
    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return self::findValueWithPresenceInData($data, $phpName, $serializedName, $convention)['value'];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array{found: bool, value: mixed}
     */
    protected static function findValueWithPresenceInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): array {
        if (array_key_exists($phpName, $data)) {
            return ['found' => true, 'value' => $data[$phpName]];
        }

        if ($phpName !== $serializedName && array_key_exists($serializedName, $data)) {
            return ['found' => true, 'value' => $data[$serializedName]];
        }

        if (null !== $convention) {
            foreach (array_keys($data) as $key) {
                if (is_string($key) && MetadataCache::conventionMatches($key, $phpName, $convention)) {
                    return ['found' => true, 'value' => $data[$key]];
                }
            }
        }

        return ['found' => false, 'value' => null];
    }

    /**
     * Find key in data using multiple lookup strategies.
     */
    /**
     * @param array<array-key, mixed> $data
     */
    protected static function hasValueSetInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): bool {
        return (bool) self::findValueWithPresenceInData($data, $phpName, $serializedName, $convention)['found'];
    }

    /**
     * Get the class-level naming convention if defined.
     *
     * @param string $class Class name
     * @return NamingConvention|null The naming convention or null if not defined
     */
    protected static function getClassConvention(string $class): ?NamingConvention
    {
        try {
            /** @var class-string $class */
            $reflection = ReflectionCache::getClass($class);
            $conventionAttrs = $reflection->getAttributes(SerializationConvention::class, ReflectionAttribute::IS_INSTANCEOF);

            if (empty($conventionAttrs)) {
                return null;
            }

            $conventionAttr = $conventionAttrs[0]->newInstance();

            // Only return if bidirectional is enabled
            return $conventionAttr->bidirectional ? $conventionAttr->getConvention() : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

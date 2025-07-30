<?php

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
     * @param array $data Input data
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
        // Strategy 1: Direct PHP name match
        if (array_key_exists($phpName, $data)) {
            return $data[$phpName];
        }

        // Strategy 2: Configured serialized name match
        if ($phpName !== $serializedName && array_key_exists($serializedName, $data)) {
            return $data[$serializedName];
        }

        // Strategy 3: Convention-based lookup (bidirectional)
        if (null !== $convention) {
            // Try to find a key that would convert to our PHP property name via the convention
            foreach (array_keys($data) as $key) {
                if (MetadataCache::conventionMatches($key, $phpName, $convention)) {
                    return $data[$key];
                }
            }
        }

        return null;
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

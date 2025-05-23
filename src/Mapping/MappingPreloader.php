<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Mapping\Exceptions\MappingException;

/**
 * Utility for preloading common mapping configurations.
 */
class MappingPreloader
{
    /**
     * Preload mapping configurations for a list of type pairs.
     *
     * @param AutoMapper $mapper Mapper to preload for
     * @param array $typePairs Array of [sourceType, destinationType] pairs
     * @return int Number of preloaded mappings
     * @throws MappingException
     */
    public static function preload(AutoMapper $mapper, array $typePairs): int
    {
        $count = 0;

        foreach ($typePairs as $pair) {
            list($sourceType, $destinationType) = $pair;

            // Skip if already cached
            if ($mapper->getCache()->has($sourceType, $destinationType)) {
                continue;
            }

            // Create a mapping if needed
            $mapping = $mapper->createMap($sourceType, $destinationType);

            // Force seal to generate the mapping configuration
            if (!$mapping->isSealed()) {
                $mapping->seal();
            }

            $count++;
        }

        return $count;
    }

    /**
     * Preload common DTO-Entity pairs from a given namespace.
     *
     * @param AutoMapper $mapper Mapper to preload for
     * @param string $namespace Namespace to scan
     * @param array $suffixes Array of suffixes to match (e.g. ['DTO', 'Entity'])
     * @return int Number of preloaded mappings
     * @throws MappingException
     */
    public static function preloadFromNamespace(AutoMapper $mapper, string $namespace, array $suffixes = ['DTO', 'Entity']): int
    {
        $count = 0;
        $classes = self::scanNamespace($namespace);
        $pairs = [];

        // Group classes by base name
        $grouped = [];
        foreach ($classes as $class) {
            foreach ($suffixes as $suffix) {
                if (str_ends_with($class, $suffix)) {
                    $baseName = substr($class, 0, -strlen($suffix));
                    $grouped[$baseName][$suffix] = $class;
                    break;
                }
            }
        }

        // Create pairs
        foreach ($grouped as $classes) {
            if (count($classes) >= 2) {
                $types = array_values($classes);
                $pairs[] = [$types[0], $types[1]];
                $pairs[] = [$types[1], $types[0]]; // Bidirectional
            }
        }

        return self::preload($mapper, $pairs);
    }

    /**
     * Scan a namespace for classes.
     *
     * @param string $namespace Namespace to scan
     * @return array Array of class names
     */
    private static function scanNamespace(string $namespace): array
    {
        // Simple implementation - in a real application this would use
        // composer's ClassLoader or a similar mechanism
        $classes = [];

        // For this example, we'll just return an empty array
        // In a real implementation, you would use reflection or composer to find classes

        return $classes;
    }
}
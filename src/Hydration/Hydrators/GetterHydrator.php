<?php

namespace Ninja\Granite\Hydration\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use Throwable;

/**
 * Hydrator for extracting data via getter methods (Phase 2).
 *
 * This hydrator works as a decorator, enriching data already extracted
 * by other hydrators. It tries multiple getter patterns:
 * - getName() for 'name'
 * - get_name() for 'name'
 * - getFullName() for 'fullName' or 'full_name'
 * - isActive() for 'active' or 'isActive' (boolean properties)
 * - hasPermission() for 'permission' or 'hasPermission'
 */
class GetterHydrator extends AbstractHydrator
{
    protected int $priority = 60;

    public function supports(mixed $data, string $targetClass): bool
    {
        // Only supports objects (but not arrays or primitives)
        return is_object($data);
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var object $data */
        return $this->extractViaGetters($data, [], $targetClass);
    }

    /**
     * Extract data from object using getter methods.
     *
     * @param object $source Source object
     * @param array $existingData Already extracted data (to avoid duplicates)
     * @param string $targetClass Target class being hydrated
     * @return array Additional data extracted via getters
     */
    public function extractViaGetters(object $source, array $existingData, string $targetClass): array
    {
        $data = [];

        // Get target properties from the destination class
        try {
            /** @var class-string $targetClass */
            $targetProperties = ReflectionCache::getPublicProperties($targetClass);
        } catch (ReflectionException $e) {
            // If we can't get reflection, just return empty
            return $data;
        }

        foreach ($targetProperties as $property) {
            $propertyName = $property->getName();

            // Skip if we already have this property
            if (isset($existingData[$propertyName])) {
                continue;
            }

            // Try various getter patterns
            $getterPatterns = $this->buildGetterPatterns($propertyName, $property->getType());

            foreach ($getterPatterns as $getter) {
                if (method_exists($source, $getter)) {
                    try {
                        $value = $source->{$getter}();
                        $data[$propertyName] = $value;
                        break; // Found a matching getter, stop trying
                    } catch (Throwable $e) {
                        // Getter threw exception, try next pattern
                        continue;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Build getter method patterns for a property name.
     *
     * @param string $propertyName Property name (e.g., 'name', 'fullName', 'is_active')
     * @param ReflectionType|null $type Property type hint
     * @return array<string> List of getter method names to try
     */
    protected function buildGetterPatterns(string $propertyName, ?ReflectionType $type): array
    {
        $patterns = [];

        // Check if it's a boolean property
        $isBool = $type && ! $type->allowsNull() &&
                  ($type instanceof ReflectionNamedType && 'bool' === $type->getName());

        // Pattern 1: Standard camelCase getter - getName()
        $camelCase = $this->snakeToCamel($propertyName);
        $patterns[] = 'get' . ucfirst($camelCase);

        // Pattern 2: snake_case getter - get_name() or get_full_name()
        // Always try snake_case variant for compatibility
        $patterns[] = 'get_' . $propertyName;

        // Pattern 3: For properties starting with 'is' or 'has', try without prefix
        if (str_starts_with($propertyName, 'is')) {
            $withoutPrefix = substr($propertyName, 2);
            $patterns[] = 'is' . ucfirst($withoutPrefix);
            $patterns[] = 'get' . ucfirst($withoutPrefix);
        }

        if (str_starts_with($propertyName, 'has')) {
            $withoutPrefix = substr($propertyName, 3);
            $patterns[] = 'has' . ucfirst($withoutPrefix);
            $patterns[] = 'get' . ucfirst($withoutPrefix);
        }

        // Pattern 4: Boolean-specific patterns
        if ($isBool) {
            // isActive() for 'active' property
            $patterns[] = 'is' . ucfirst($camelCase);
            // hasPermission() for 'permission' property
            $patterns[] = 'has' . ucfirst($camelCase);
        }

        // Pattern 5: Direct property name as method (rare but exists)
        $patterns[] = $propertyName;

        // Remove duplicates while preserving order
        return array_values(array_unique($patterns));
    }

    /**
     * Convert snake_case to camelCase.
     *
     * @param string $string Snake case string
     * @return string Camel case string
     */
    protected function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }
}

<?php

namespace Ninja\Granite\Serialization;

use Exception;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Conventions\CamelCaseConvention;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;

class MetadataCache
{
    /**
     * Cache of serialization metadata by class name.
     *
     * @var array<string, Metadata>
     */
    private static array $metadataCache = [];

    /**
     * Get serialization metadata for a class.
     *
     * @param class-string $class Class name
     * @return Metadata Serialization metadata
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public static function getMetadata(string $class): Metadata
    {
        if ( ! isset(self::$metadataCache[$class])) {
            self::$metadataCache[$class] = self::buildMetadata($class);
        }

        return self::$metadataCache[$class];
    }

    /**
     * Check if a data key could represent the given PHP property via the convention.
     *
     * @param string $dataKey Key from input data
     * @param string $phpPropertyName PHP property name
     * @param NamingConvention $convention The naming convention to use for comparison
     * @return bool Whether they represent the same logical property
     */
    public static function conventionMatches(string $dataKey, string $phpPropertyName, NamingConvention $convention): bool
    {
        try {
            // Strategy 1: Normalize the data key using the target convention
            $dataKeyNormalized = null;
            if ($convention->matches($dataKey)) {
                $dataKeyNormalized = $convention->normalize($dataKey);
            }

            // Strategy 2: Normalize the PHP property name (assume camelCase source)
            $sourceConvention = new CamelCaseConvention();
            $phpPropertyNormalized = null;

            if ($sourceConvention->matches($phpPropertyName)) {
                $phpPropertyNormalized = $sourceConvention->normalize($phpPropertyName);
            } else {
                // Fallback normalization for PHP property names
                $converted = preg_replace('/(?<!^)([A-Z])/', ' $1', $phpPropertyName);
                $phpPropertyNormalized = mb_strtolower($converted ?? $phpPropertyName);
            }

            // Strategy 3: Compare normalized forms
            if (null !== $dataKeyNormalized) {
                return $dataKeyNormalized === $phpPropertyNormalized;
            }

            // Strategy 4: Try direct conversion - convert PHP property to target convention and compare
            $phpPropertyInTargetConvention = self::convertPropertyNameToConvention($phpPropertyName, $convention);
            return (bool) ($phpPropertyInTargetConvention === $dataKey)



            ;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clear the metadata cache.
     * Useful for testing or when class definitions change dynamically.
     */
    public static function clearCache(): void
    {
        self::$metadataCache = [];
    }

    /**
     * Build serialization metadata for a class.
     *
     * @param class-string $class Class name
     * @return Metadata Built metadata
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private static function buildMetadata(string $class): Metadata
    {
        $metadata = new Metadata();
        $reflection = ReflectionCache::getClass($class);

        // Check for class-level SerializationConvention attribute
        $classConvention = self::getClassConvention($class);

        // Check if the class has serializedNames() method and call it using reflection
        if (method_exists($class, 'serializedNames')) {
            $propertyNames = self::invokeProtectedStaticMethod($class, 'serializedNames');
            if (is_array($propertyNames)) {
                foreach ($propertyNames as $propName => $serializedName) {
                    if (is_string($propName) && is_string($serializedName)) {
                        $metadata->mapPropertyName($propName, $serializedName);
                    }
                }
            }
        }

        // Check if the class has hiddenProperties() method and call it using reflection
        if (method_exists($class, 'hiddenProperties')) {
            $hiddenProps = self::invokeProtectedStaticMethod($class, 'hiddenProperties');
            if (is_array($hiddenProps)) {
                foreach ($hiddenProps as $propName) {
                    if (is_string($propName)) {
                        $metadata->hideProperty($propName);
                    }
                }
            }
        }

        // Check for property attributes (PHP 8+)
        $properties = ReflectionCache::getPublicProperties($class);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Check for SerializedName attribute (takes precedence over convention)
            $nameAttrs = $property->getAttributes(SerializedName::class, ReflectionAttribute::IS_INSTANCEOF);
            if ( ! empty($nameAttrs)) {
                $attr = $nameAttrs[0]->newInstance();
                $metadata->mapPropertyName($propertyName, $attr->name);
            }
            // Apply class convention if no explicit SerializedName
            elseif (null !== $classConvention) {
                $conventionName = self::convertPropertyNameToConvention($propertyName, $classConvention);
                if ($conventionName !== $propertyName) {
                    $metadata->mapPropertyName($propertyName, $conventionName);
                }
            }

            // Check for Hidden attribute
            $hiddenAttrs = $property->getAttributes(Hidden::class, ReflectionAttribute::IS_INSTANCEOF);
            if ( ! empty($hiddenAttrs)) {
                $metadata->hideProperty($propertyName);
            }
        }

        return $metadata;
    }

    /**
     * Get the class-level naming convention if defined.
     *
     * @param class-string $class Class name
     * @return NamingConvention|null The naming convention or null if not defined
     */
    private static function getClassConvention(string $class): ?NamingConvention
    {
        try {
            $reflection = ReflectionCache::getClass($class);
            $conventionAttrs = $reflection->getAttributes(SerializationConvention::class, ReflectionAttribute::IS_INSTANCEOF);

            if (empty($conventionAttrs)) {
                return null;
            }

            $conventionAttr = $conventionAttrs[0]->newInstance();
            return $conventionAttr->getConvention();
        } catch (Exception $e) {
            // Log error and return null to fallback gracefully
            return null;
        }
    }

    /**
     * Convert a PHP property name (assumed camelCase) to the target convention.
     *
     * @param string $propertyName The PHP property name (typically camelCase)
     * @param NamingConvention $targetConvention The target naming convention
     * @return string The converted property name
     */
    private static function convertPropertyNameToConvention(string $propertyName, NamingConvention $targetConvention): string
    {
        try {
            // Step 1: Detect the source convention (assume camelCase for PHP properties)
            $sourceConvention = new CamelCaseConvention();

            // Step 2: Normalize the property name to a standard form
            $normalized = $propertyName;

            // If the property name matches camelCase convention, normalize it
            if ($sourceConvention->matches($propertyName)) {
                $normalized = $sourceConvention->normalize($propertyName);
            } else {
                // Fallback: try to convert camelCase-like strings to normalized form
                // Example: "firstName" -> "first name", "XMLHttpRequest" -> "xml http request"
                $converted = preg_replace('/(?<!^)([A-Z])/', ' $1', $propertyName);
                $normalized = mb_strtolower($converted ?? $propertyName);
            }

            // Step 3: Convert normalized form to target convention
            return $targetConvention->denormalize($normalized);

        } catch (Exception $e) {
            // Return original name if conversion fails
            return $propertyName;
        }
    }

    /**
     * Invoke a protected static method using reflection.
     *
     * @param string $class Class name
     * @param string $methodName Method name
     * @return mixed Method result
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private static function invokeProtectedStaticMethod(string $class, string $methodName): mixed
    {
        try {
            $reflectionMethod = new ReflectionMethod($class, $methodName);
            $reflectionMethod->setAccessible(true);
            return $reflectionMethod->invoke(null);
        } catch (ReflectionException $e) {
            throw new \Ninja\Granite\Exceptions\ReflectionException($class, $methodName, $e->getMessage());
        }
    }
}

<?php

namespace Ninja\Granite\Serialization;

use Ninja\Granite\Serialization\Attributes\Hidden;
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
     * @param string $class Class name
     * @return Metadata Serialization metadata
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public static function getMetadata(string $class): Metadata
    {
        if (!isset(self::$metadataCache[$class])) {
            self::$metadataCache[$class] = self::buildMetadata($class);
        }

        return self::$metadataCache[$class];
    }

    /**
     * Build serialization metadata for a class.
     *
     * @param string $class Class name
     * @return Metadata Built metadata
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    private static function buildMetadata(string $class): Metadata
    {
        $metadata = new Metadata();
        $reflection = ReflectionCache::getClass($class);

        // Check if the class has serializedNames() method and call it using reflection
        if (method_exists($class, 'serializedNames')) {
            $propertyNames = self::invokeProtectedStaticMethod($class, 'serializedNames');
            foreach ($propertyNames as $propName => $serializedName) {
                $metadata->mapPropertyName($propName, $serializedName);
            }
        }

        // Check if the class has hiddenProperties() method and call it using reflection
        if (method_exists($class, 'hiddenProperties')) {
            $hiddenProps = self::invokeProtectedStaticMethod($class, 'hiddenProperties');
            foreach ($hiddenProps as $propName) {
                $metadata->hideProperty($propName);
            }
        }

        // Check for property attributes (PHP 8+)
        $properties = ReflectionCache::getPublicProperties($class);

        foreach ($properties as $property) {
            // Check for SerializedName attribute
            $nameAttrs = $property->getAttributes(SerializedName::class, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($nameAttrs)) {
                $attr = $nameAttrs[0]->newInstance();
                $metadata->mapPropertyName($property->getName(), $attr->name);
            }

            // Check for Hidden attribute
            $hiddenAttrs = $property->getAttributes(Hidden::class, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($hiddenAttrs)) {
                $metadata->hideProperty($property->getName());
            }
        }

        return $metadata;
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
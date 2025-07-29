<?php

namespace Ninja\Granite\Support;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Utility class to cache reflection objects and improve performance.
 */
final class ReflectionCache
{
    /**
     * Cache for reflection classes.
     *
     * @var array<string, ReflectionClass>
     */
    private static array $classCache = [];

    /**
     * Cache for class properties.
     *
     * @var array<string, ReflectionProperty[]>
     */
    private static array $propertiesCache = [];

    /**
     * Get a cached ReflectionClass instance.
     *
     * @param string $class Class name
     * @return ReflectionClass Reflection instance
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    /**
     * @param class-string $class
     */
    public static function getClass(string $class): ReflectionClass
    {
        try {
            if ( ! isset(self::$classCache[$class])) {
                /** @phpstan-ignore-next-line ReflectionClass can throw ReflectionException */
                self::$classCache[$class] = new ReflectionClass($class);
            }

            return self::$classCache[$class];
        } catch (ReflectionException $e) {
            throw \Ninja\Granite\Exceptions\ReflectionException::classNotFound($class);
        }
    }

    /**
     * Get cached public properties for a class.
     *
     * @param class-string $class Class name
     * @return ReflectionProperty[] Array of reflection properties
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public static function getPublicProperties(string $class): array
    {
        if ( ! isset(self::$propertiesCache[$class])) {
            $reflection = self::getClass($class);
            self::$propertiesCache[$class] = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        }

        return self::$propertiesCache[$class];
    }
}

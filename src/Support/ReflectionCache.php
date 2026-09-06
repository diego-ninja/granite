<?php
// ABOUTME: Defines ReflectionCache as part of shared reflection, comparison and date support.
// ABOUTME: Owns the ReflectionCache boundary within shared reflection, comparison and date support.

namespace Ninja\Granite\Support;

use ReflectionClass;
use ReflectionProperty;

/**
 * Utility class to cache reflection objects and improve performance.
 */
final class ReflectionCache
{
    /**
     * Cache for reflection classes.
     *
     * @var array<string, ReflectionClass<object>>
     */
    private static array $classCache = [];

    /**
     * Cache for class properties.
     *
     * @var array<string, ReflectionProperty[]>
     */
    private static array $propertiesCache = [];

    /**
     * Cache for class profiles.
     *
     * @var array<string, ClassProfile>
     */
    private static array $profileCache = [];

    /**
     * Get a cached ReflectionClass instance.
     *
     * @param string $class Class name
     * @return ReflectionClass<object> Reflection instance
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    /** @return ReflectionClass<object> */
    public static function getClass(string $class): ReflectionClass
    {
        if ( ! class_exists($class)) {
            throw \Ninja\Granite\Exceptions\ReflectionException::classNotFound($class);
        }

        if ( ! isset(self::$classCache[$class])) {
            self::$classCache[$class] = new ReflectionClass($class);
        }

        return self::$classCache[$class];
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

    /**
     * Get cached class profile for fast-path detection.
     *
     * @param class-string $class Class name
     * @return ClassProfile Class profile
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public static function getClassProfile(string $class): ClassProfile
    {
        if ( ! isset(self::$profileCache[$class])) {
            self::$profileCache[$class] = ClassProfile::build($class);
        }

        return self::$profileCache[$class];
    }
}

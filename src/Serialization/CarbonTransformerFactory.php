<?php
// ABOUTME: Defines CarbonTransformerFactory as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the CarbonTransformerFactory boundary between metadata and serialized values.

// ABOUTME: Combines Carbon property, class, and global configuration.
// ABOUTME: Produces one transformer with deterministic precedence rules.

declare(strict_types=1);

namespace Ninja\Granite\Serialization;

use Exception;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionAttribute;
use ReflectionProperty;

final class CarbonTransformerFactory
{
    /** @var array<string, CarbonTransformer|null> */
    private static array $cache = [];

    /** @var array<class-string, DateTimeProvider|null> */
    private static array $classProviderCache = [];

    public static function create(
        ReflectionProperty $property,
        ?DateTimeProvider $classProvider = null,
    ): ?CarbonTransformer {
        $cacheKey = self::cacheKey($property, $classProvider);
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $carbonDate = self::attribute($property, CarbonDate::class);
        $carbonRange = self::attribute($property, CarbonRange::class);
        $carbonRelative = self::attribute($property, CarbonRelative::class);
        $config = GraniteConfig::getInstance();

        if (null === $carbonDate && null === $carbonRange && null === $carbonRelative
            && (null === $classProvider || ! $classProvider->isCarbonProvider())) {
            self::$cache[$cacheKey] = null;
            return self::$cache[$cacheKey];
        }

        $format = null !== $carbonDate ? $carbonDate->format : null;
        $format ??= null !== $classProvider ? $classProvider->format : $config->getCarbonParseFormat();
        $timezone = null !== $carbonDate ? $carbonDate->timezone : null;
        $timezone ??= null !== $classProvider ? $classProvider->timezone : $config->getCarbonTimezone();
        $locale = null !== $carbonDate ? $carbonDate->locale : null;
        $locale ??= null !== $classProvider ? $classProvider->locale : $config->getCarbonLocale();
        $serializeFormat = null !== $carbonDate ? $carbonDate->serializeFormat : null;
        $serializeFormat ??= null !== $classProvider ? $classProvider->serializeFormat : $config->getCarbonSerializeFormat();

        self::$cache[$cacheKey] = new CarbonTransformer(
            format: $format,
            timezone: $timezone,
            locale: $locale,
            immutable: null !== $carbonDate
                ? $carbonDate->immutable
                : (null !== $classProvider ? $classProvider->isCarbonImmutable() : $config->shouldPreferCarbonImmutable()),
            parseRelative: null !== $carbonDate
                ? $carbonDate->parseRelative
                : (null !== $carbonRelative
                    ? $carbonRelative->enabled
                    : (null !== $classProvider ? $classProvider->parseRelative : $config->isCarbonParseRelativeEnabled())),
            serializeFormat: $serializeFormat,
            serializeTimezone: null !== $carbonDate ? $carbonDate->serializeTimezone : $config->getCarbonSerializeTimezone(),
            min: null !== $carbonDate ? $carbonDate->min : (null !== $carbonRange ? $carbonRange->min : null),
            max: null !== $carbonDate ? $carbonDate->max : (null !== $carbonRange ? $carbonRange->max : null),
            relativeBaseDate: null !== $carbonRelative ? $carbonRelative->baseDate : null,
        );

        return self::$cache[$cacheKey];
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /** @param class-string $class */
    public static function classProvider(string $class): ?DateTimeProvider
    {
        if (array_key_exists($class, self::$classProviderCache)) {
            return self::$classProviderCache[$class];
        }

        try {
            $reflection = ReflectionCache::getClass($class);
            $attributes = $reflection->getAttributes(DateTimeProvider::class, ReflectionAttribute::IS_INSTANCEOF);
            self::$classProviderCache[$class] = [] === $attributes ? null : $attributes[0]->newInstance();
        } catch (Exception) {
            self::$classProviderCache[$class] = null;
        }

        return self::$classProviderCache[$class];
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private static function attribute(ReflectionProperty $property, string $attributeClass): ?object
    {
        $attributes = $property->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();
        return $attribute instanceof $attributeClass ? $attribute : null;
    }

    private static function cacheKey(ReflectionProperty $property, ?DateTimeProvider $classProvider): string
    {
        return $property->getDeclaringClass()->getName()
            . '::' . $property->getName()
            . ':' . (null === $classProvider ? '' : serialize($classProvider));
    }
}

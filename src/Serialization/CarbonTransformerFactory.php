<?php

// ABOUTME: Combines Carbon property, class, and global configuration.
// ABOUTME: Produces one transformer with deterministic precedence rules.

declare(strict_types=1);

namespace Ninja\Granite\Serialization;

use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionAttribute;
use ReflectionProperty;

final class CarbonTransformerFactory
{
    public static function create(
        ReflectionProperty $property,
        ?DateTimeProvider $classProvider = null,
    ): ?CarbonTransformer {
        $carbonDate = self::attribute($property, CarbonDate::class);
        $carbonRange = self::attribute($property, CarbonRange::class);
        $carbonRelative = self::attribute($property, CarbonRelative::class);
        $config = GraniteConfig::getInstance();

        if (null === $carbonDate && null === $carbonRange && null === $carbonRelative
            && (null === $classProvider || ! $classProvider->isCarbonProvider())) {
            return null;
        }

        $format = null !== $carbonDate ? $carbonDate->format : null;
        $format ??= null !== $classProvider ? $classProvider->format : $config->getCarbonParseFormat();
        $timezone = null !== $carbonDate ? $carbonDate->timezone : null;
        $timezone ??= null !== $classProvider ? $classProvider->timezone : $config->getCarbonTimezone();
        $locale = null !== $carbonDate ? $carbonDate->locale : null;
        $locale ??= null !== $classProvider ? $classProvider->locale : $config->getCarbonLocale();
        $serializeFormat = null !== $carbonDate ? $carbonDate->serializeFormat : null;
        $serializeFormat ??= null !== $classProvider ? $classProvider->serializeFormat : $config->getCarbonSerializeFormat();

        return new CarbonTransformer(
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
}

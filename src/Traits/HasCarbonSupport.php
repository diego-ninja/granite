<?php

namespace Ninja\Granite\Traits;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * Trait providing Carbon-specific functionality for Granite objects.
 * Handles Carbon date conversion, serialization, and attribute processing.
 */
trait HasCarbonSupport
{
    /**
     * Convert value to Carbon instance.
     *
     * @param mixed $value Value to convert
     * @param string $typeName Target Carbon type name
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return DateTimeInterface|null Carbon instance
     */
    protected static function convertToCarbon(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface {
        if ( ! CarbonSupport::isAvailable()) {
            return null;
        }

        // Check for Carbon-specific attributes
        $carbonTransformer = self::getCarbonTransformerFromAttributes($property, $classProvider);

        if (null !== $carbonTransformer) {
            return $carbonTransformer->transform($value);
        }

        // Fallback to basic Carbon creation
        $immutable = CarbonSupport::isCarbonImmutable($typeName);
        return CarbonSupport::create($value, null, null, $immutable);
    }

    /**
     * Convert value to DateTime instance (with possible Carbon auto-conversion).
     *
     * @param mixed $value Value to convert
     * @param string $typeName Target DateTime type name
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return DateTimeInterface|null DateTime instance
     * @throws DateMalformedStringException
     */
    protected static function convertToDateTime(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface {
        // Check if global config suggests auto-converting to Carbon
        $config = GraniteConfig::getInstance();
        if ($config->shouldAutoConvertToCarbon($typeName)) {
            $carbonResult = self::convertToCarbon($value, $config->getPreferredDateTimeClass(), $property, $classProvider);
            if (null !== $carbonResult) {
                return $carbonResult;
            }
        }

        // Check for class-level DateTime provider
        if (null !== $classProvider && $classProvider->isCarbonProvider()) {
            return self::convertToCarbon($value, $classProvider->provider, $property, $classProvider);
        }

        // Standard DateTime conversion
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get Carbon transformer from property attributes.
     *
     * @param ReflectionProperty|null $property Property to check for attributes
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return CarbonTransformer|null Carbon transformer or null
     */
    protected static function getCarbonTransformerFromAttributes(
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?CarbonTransformer {
        if (null === $property) {
            return null;
        }

        // Check for CarbonDate attribute (most comprehensive)
        $carbonDateAttrs = $property->getAttributes(CarbonDate::class, ReflectionAttribute::IS_INSTANCEOF);
        if ( ! empty($carbonDateAttrs)) {
            /** @var CarbonDate $attr */
            $attr = $carbonDateAttrs[0]->newInstance();
            return $attr->createTransformer();
        }

        // Only create transformer if we have some Carbon-specific configuration
        if ((null !== $classProvider && $classProvider->isCarbonProvider())) {
            return new CarbonTransformer(
                immutable: $classProvider->isCarbonImmutable(),
            );
        }

        return null;
    }

    /**
     * Get the class-level DateTime provider if defined.
     *
     * @param string $class Class name
     * @return DateTimeProvider|null The DateTime provider or null if not defined
     */
    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        try {
            /** @var class-string $class */
            $reflection = ReflectionCache::getClass($class);
            $providerAttrs = $reflection->getAttributes(DateTimeProvider::class, ReflectionAttribute::IS_INSTANCEOF);

            if (empty($providerAttrs)) {
                return null;
            }

            return $providerAttrs[0]->newInstance();
        } catch (Exception $e) {
            return null;
        }
    }
}

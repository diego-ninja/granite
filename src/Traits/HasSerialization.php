<?php
// ABOUTME: Defines HasSerialization as part of reusable Granite object behavior.
// ABOUTME: Owns the HasSerialization boundary within reusable Granite object behavior.

namespace Ninja\Granite\Traits;

use DateTimeInterface;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Serialization\SerializationCache;
use Ninja\Granite\Serialization\SerializationCachePolicy;
use Ninja\Granite\Serialization\ValueSerializer;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionProperty;
use RuntimeException;

/**
 * Trait providing serialization functionality for Granite objects.
 * Handles conversion of objects to arrays and JSON format.
 */
trait HasSerialization
{
    /**
     * Get Carbon transformer from property attributes.
     * This method will be provided by HasCarbonSupport trait.
     *
     * @param ReflectionProperty|null $property Property to check for attributes
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return CarbonTransformer|null Carbon transformer or null
     */
    abstract protected static function getCarbonTransformerFromAttributes(?ReflectionProperty $property = null, ?DateTimeProvider $classProvider = null): ?CarbonTransformer;

    /**
     * @return array Serialized array
     * @throws RuntimeException If a property cannot be serialized
     * @throws SerializationException|ReflectionException
     */
    /** @return array<array-key, mixed> */
    public function array(): array
    {
        $cacheable = SerializationCachePolicy::isCacheable($this);
        if ($cacheable) {
            $cached = SerializationCache::get($this);
            if (null !== $cached) {
                return $cached;
            }
        }

        $result = $this->computeArray();
        if ($cacheable) {
            SerializationCache::set($this, $result);
        }

        return $result;
    }

    /**
     * @throws SerializationException|ReflectionException
     */
    public function json(): string
    {
        $cacheable = SerializationCachePolicy::isCacheable($this);
        if ($cacheable) {
            $cached = SerializationCache::getJson($this);
            if (null !== $cached) {
                return $cached;
            }
        }

        $json = json_encode($this->array());
        if (false === $json) {
            throw new RuntimeException('Failed to encode object to JSON');
        }

        if ($cacheable) {
            SerializationCache::setJson($this, $json);
        }

        return $json;
    }

    /**
     * Define custom property names for serialization.
     * Override in child classes to customize property names.
     *
     * @return array<string, string> Mapping of PHP property names to serialized names
     */
    protected static function serializedNames(): array
    {
        return [];
    }

    /**
     * Define properties that should be hidden during serialization.
     * Override in child classes to hide specific properties.
     *
     * @return array<string> List of property names to hide
     */
    protected static function hiddenProperties(): array
    {
        return [];
    }

    /**
     * @return array Serialized array
     * @throws SerializationException|ReflectionException
     */
    /** @return array<array-key, mixed> */
    private function computeArray(): array
    {
        $profile = ReflectionCache::getClassProfile(static::class);
        if ($profile->canUseFastPath) {
            return $profile->toArray($this);
        }

        $result = [];
        $properties = ReflectionCache::getPublicProperties(static::class);
        $metadata = MetadataCache::getMetadata(static::class);

        foreach ($properties as $property) {
            $phpName = $property->getName();

            // Skip hidden properties
            if ($metadata->isHidden($phpName)) {
                continue;
            }

            // Skip uninitialized properties
            if ( ! $property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);
            $serializedValue = $this->serializeValue($phpName, $value, $property);

            // Use custom property name if defined (includes convention-applied names)
            $serializedName = $metadata->getSerializedName($phpName);
            $result[$serializedName] = $serializedValue;
        }

        return $result;
    }

    /**
     * @param string $propertyName The property name (for error reporting)
     * @param mixed $value The value to serialize
     * @param ReflectionProperty|null $property Property for attribute access
     * @return mixed Serialized value
     * @throws SerializationException If the value cannot be serialized
     */
    private function serializeValue(string $propertyName, mixed $value, ?ReflectionProperty $property = null): mixed
    {
        $carbonTransformer = self::getCarbonTransformerFromAttributes(
            $property,
            self::getClassDateTimeProvider(static::class),
        );
        $config = GraniteConfig::getInstance();
        $dateFormatter = static function (DateTimeInterface $date) use ($carbonTransformer, $config): string {
            if (null !== $carbonTransformer) {
                return (string) $carbonTransformer->serialize($date);
            }

            if (CarbonSupport::isCarbonInstance($date)) {
                return CarbonSupport::serialize(
                    $date,
                    $config->getCarbonSerializeFormat(),
                    $config->getCarbonSerializeTimezone(),
                );
            }

            return $date->format(DateTimeInterface::ATOM);
        };

        return ValueSerializer::serialize($value, static::class, $propertyName, $dateFormatter);
    }
}

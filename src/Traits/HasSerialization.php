<?php

namespace Ninja\Granite\Traits;

use BackedEnum;
use DateTimeInterface;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionProperty;
use RuntimeException;
use UnitEnum;

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
    public function array(): array
    {
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
     * @throws SerializationException|ReflectionException
     */
    public function json(): string
    {
        $json = json_encode($this->array());
        if (false === $json) {
            throw new RuntimeException('Failed to encode object to JSON');
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
     * @param string $propertyName The property name (for error reporting)
     * @param mixed $value The value to serialize
     * @param ReflectionProperty|null $property Property for attribute access
     * @return mixed Serialized value
     * @throws SerializationException If the value cannot be serialized
     */
    private function serializeValue(string $propertyName, mixed $value, ?ReflectionProperty $property = null): mixed
    {
        if (null === $value) {
            return null;
        }

        if (is_scalar($value) || is_array($value)) {
            return $value;
        }

        // Handle Carbon instances with custom serialization
        if (CarbonSupport::isCarbonInstance($value)) {
            $carbonTransformer = self::getCarbonTransformerFromAttributes($property, null);
            if (null !== $carbonTransformer) {
                /** @var DateTimeInterface $value */
                return $carbonTransformer->serialize($value);
            }

            // Fallback to global config
            $config = GraniteConfig::getInstance();
            /** @var DateTimeInterface $value */
            return CarbonSupport::serialize(
                $value,
                $config->getCarbonSerializeFormat(),
                $config->getCarbonSerializeTimezone(),
            );
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (interface_exists('UnitEnum') && $value instanceof UnitEnum) {
            if ($value instanceof BackedEnum) {
                return $value->value;
            }
            return $value->name;
        }

        if ($value instanceof GraniteObject) {
            return $value->array();
        }

        throw SerializationException::unsupportedType(static::class, $propertyName, get_debug_type($value));
    }
}

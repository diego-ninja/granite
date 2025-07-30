<?php

namespace Ninja\Granite;

use BackedEnum;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionAttribute;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use UnitEnum;

abstract readonly class GraniteDTO implements GraniteObject
{
    /**
     * @param string|array|GraniteObject $data Source data
     * @return static New instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    public static function from(string|array|GraniteObject $data): static
    {
        $data = self::normalizeInputData($data);
        $instance = self::createEmptyInstance();

        return self::hydrateInstance($instance, $data);
    }

    /**
     * @return array Serialized array
     * @throws RuntimeException If a property cannot be serialized
     * @throws SerializationException|Exceptions\ReflectionException
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
     * @throws SerializationException|Exceptions\ReflectionException
     */
    public function json(): string
    {
        $json = json_encode($this->array());
        if (false === $json) {
            throw new RuntimeException('Failed to encode object to JSON');
        }
        return $json;
    }

    protected static function normalizeInputData(string|array|GraniteObject $data): array
    {
        if ($data instanceof GraniteObject) {
            return $data->array();
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if ( ! is_array($decoded)) {
                throw new InvalidArgumentException('Invalid JSON string provided');
            }
            return $decoded;
        }

        return $data;
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
     * @throws Exceptions\ReflectionException
     */
    private static function createEmptyInstance(): object
    {
        try {
            $reflection = ReflectionCache::getClass(static::class);
            return $reflection->newInstanceWithoutConstructor();
        } catch (ReflectionException $e) {
            throw Exceptions\ReflectionException::classNotFound(static::class);
        }
    }

    /**
     * @param object $instance Instance to hydrate
     * @param array $data Data to hydrate with
     * @return static Hydrated instance
     * @throws DateMalformedStringException
     * @throws Exceptions\ReflectionException
     */
    private static function hydrateInstance(object $instance, array $data): static
    {
        $properties = ReflectionCache::getPublicProperties(static::class);

        // Get serialization metadata and class convention
        $metadata = MetadataCache::getMetadata(static::class);
        $classConvention = self::getClassConvention(static::class);
        $classDateTimeProvider = self::getClassDateTimeProvider(static::class);

        // Process each property
        foreach ($properties as $property) {
            $phpName = $property->getName();
            $serializedName = $metadata->getSerializedName($phpName);

            // Try to find the value in the input data with multiple strategies
            $value = self::findValueInData($data, $phpName, $serializedName, $classConvention);

            // Skip if property is not found in data
            if (null === $value && ! array_key_exists($phpName, $data) &&
                ! array_key_exists($serializedName, $data)) {
                continue;
            }

            $type = $property->getType();
            $convertedValue = self::convertValueToType($value, $type, $property, $classDateTimeProvider);
            $property->setValue($instance, $convertedValue);
        }

        /** @var static $result */
        $result = $instance;
        return $result;
    }

    /**
     * Find value in data using multiple lookup strategies.
     *
     * @param array $data Input data
     * @param string $phpName PHP property name
     * @param string $serializedName Configured serialized name
     * @param NamingConvention|null $convention Class convention
     * @return mixed Found value or null
     */
    private static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        // Strategy 1: Direct PHP name match
        if (array_key_exists($phpName, $data)) {
            return $data[$phpName];
        }

        // Strategy 2: Configured serialized name match
        if ($phpName !== $serializedName && array_key_exists($serializedName, $data)) {
            return $data[$serializedName];
        }

        // Strategy 3: Convention-based lookup (bidirectional)
        if (null !== $convention) {
            // Try to find a key that would convert to our PHP property name via the convention
            foreach (array_keys($data) as $key) {
                if (MetadataCache::conventionMatches($key, $phpName, $convention)) {
                    return $data[$key];
                }
            }
        }

        return null;
    }

    /**
     * Get the class-level naming convention if defined.
     *
     * @param string $class Class name
     * @return NamingConvention|null The naming convention or null if not defined
     */
    private static function getClassConvention(string $class): ?NamingConvention
    {
        try {
            /** @var class-string $class */
            $reflection = ReflectionCache::getClass($class);
            $conventionAttrs = $reflection->getAttributes(SerializationConvention::class, ReflectionAttribute::IS_INSTANCEOF);

            if (empty($conventionAttrs)) {
                return null;
            }

            $conventionAttr = $conventionAttrs[0]->newInstance();

            // Only return if bidirectional is enabled
            return $conventionAttr->bidirectional ? $conventionAttr->getConvention() : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get the class-level DateTime provider if defined.
     *
     * @param string $class Class name
     * @return DateTimeProvider|null The DateTime provider or null if not defined
     */
    private static function getClassDateTimeProvider(string $class): ?DateTimeProvider
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

    /**
     * @param mixed $value The value to convert
     * @param ReflectionType|null $type The target type
     * @param ReflectionProperty|null $property The property being converted (for attribute access)
     * @param DateTimeProvider|null $classProvider Class-level DateTime provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     */
    private static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        if (null === $value) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            return self::convertToNamedType($value, $type, $property, $classProvider);
        }

        if ($type instanceof ReflectionUnionType) {
            return self::convertToUnionType($value, $type, $property, $classProvider);
        }

        return $value;
    }

    /**
     * @param mixed $value The value to convert
     * @param ReflectionNamedType $type The target type
     * @param ReflectionProperty|null $property The property being converted
     * @param DateTimeProvider|null $classProvider Class-level DateTime provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private static function convertToNamedType(
        mixed $value,
        ReflectionNamedType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        $typeName = $type->getName();

        // Check for Carbon classes first (before general DateTime check)
        if (CarbonSupport::isCarbonClass($typeName)) {
            return self::convertToCarbon($value, $typeName, $property, $classProvider);
        }

        // Check for GraniteObject first
        if (is_subclass_of($typeName, GraniteObject::class)) {
            if (null === $value) {
                return null;
            }
            if (is_array($value) || is_string($value) || $value instanceof GraniteObject) {
                return $typeName::from($value);
            }
            return null;
        }

        // Check for DateTime
        if (DateTimeInterface::class === $typeName || is_subclass_of($typeName, DateTimeInterface::class)) {
            return self::convertToDateTime($value, $typeName, $property, $classProvider);
        }

        // Check for Enum (PHP 8.1+)
        if (interface_exists('UnitEnum') && is_subclass_of($typeName, UnitEnum::class)) {
            // If value is already the correct enum instance
            if ($value instanceof $typeName) {
                return $value;
            }

            // Try to convert string/int to an enum case
            if (is_string($value) || is_int($value)) {
                // For BackedEnum (with values)
                if (is_subclass_of($typeName, BackedEnum::class)) {
                    return $typeName::tryFrom($value);
                }

                // For UnitEnum (without values)
                foreach ($typeName::cases() as $case) {
                    if ($case->name === $value) {
                        return $case;
                    }
                }
            }

            // If we couldn't convert to an enum, return null
            return null;
        }

        return $value;
    }

    /**
     * Convert value to Carbon instance.
     *
     * @param mixed $value Value to convert
     * @param string $typeName Target Carbon type name
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return DateTimeInterface|null Carbon instance
     */
    private static function convertToCarbon(
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
    private static function convertToDateTime(
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
    private static function getCarbonTransformerFromAttributes(
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
     * @param mixed $value The value to convert
     * @param ReflectionUnionType $type The union type
     * @param ReflectionProperty|null $property Property for attribute access
     * @param DateTimeProvider|null $classProvider Class-level provider
     * @return mixed Converted value
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private static function convertToUnionType(
        mixed $value,
        ReflectionUnionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType) {
                $typeName = $unionType->getName();

                // Try Carbon types first
                if (CarbonSupport::isCarbonClass($typeName)) {
                    $result = self::convertToCarbon($value, $typeName, $property, $classProvider);
                    if (null !== $result) {
                        return $result;
                    }
                }

                // Try DateTime types
                if (DateTimeInterface::class === $typeName || is_subclass_of($typeName, DateTimeInterface::class)) {
                    $result = self::convertToDateTime($value, $typeName, $property, $classProvider);
                    if (null !== $result) {
                        return $result;
                    }
                }
            }
        }

        return $value;
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
            $carbonTransformer = self::getCarbonTransformerFromAttributes($property);
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

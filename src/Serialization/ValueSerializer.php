<?php
// ABOUTME: Defines ValueSerializer as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the ValueSerializer boundary between metadata and serialized values.

// ABOUTME: Normalizes values into arrays and scalars suitable for Granite serialization.
// ABOUTME: Recurses through arrays while preserving keys and reporting the complete value path on failure.

declare(strict_types=1);

namespace Ninja\Granite\Serialization;

use BackedEnum;
use DateTimeInterface;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\SerializationException;
use UnitEnum;

final class ValueSerializer
{
    /**
     * @param callable(DateTimeInterface): mixed|null $dateFormatter
     */
    public static function serialize(
        mixed $value,
        string $objectType,
        string $propertyPath,
        ?callable $dateFormatter = null,
    ): mixed {
        if (null === $value || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $itemPath = $propertyPath . '.' . (string) $key;
                $result[$key] = self::serialize($item, $objectType, $itemPath, $dateFormatter);
            }

            return $result;
        }

        if ($value instanceof DateTimeInterface) {
            return null !== $dateFormatter
                ? $dateFormatter($value)
                : $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof GraniteObject) {
            return $value->array();
        }

        throw SerializationException::unsupportedType($objectType, $propertyPath, get_debug_type($value));
    }
}

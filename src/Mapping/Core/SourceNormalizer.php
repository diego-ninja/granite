<?php

namespace Ninja\Granite\Mapping\Core;

use Closure;
use JsonSerializable;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * Normalizes source data to array format for consistent processing.
 */
final readonly class SourceNormalizer
{
    /**
     * @throws MappingException
     */
    /** @return array<array-key, mixed> */
    public function normalize(mixed $source): array
    {
        if (is_array($source)) {
            return $source;
        }

        if ( ! is_object($source)) {
            throw MappingException::unsupportedSourceType($source);
        }

        if ($source instanceof GraniteObject) {
            return $this->normalizeWithAdapter($source, 'GraniteObject::array()', $source->array(...));
        }

        if (is_callable([$source, 'toArray'])) {
            return $this->normalizeWithAdapter($source, 'toArray()', Closure::fromCallable([$source, 'toArray']));
        }

        if ($source instanceof JsonSerializable) {
            return $this->normalizeWithAdapter($source, 'JsonSerializable::jsonSerialize()', $source->jsonSerialize(...));
        }

        if ($source instanceof stdClass) {
            return (array) $source;
        }

        return $this->objectToArray($source);
    }

    /**
     * @throws MappingException
     */
    /** @return array<array-key, mixed> */
    private function objectToArray(object $source): array
    {
        try {
            $result = [];
            $properties = ReflectionCache::getPublicProperties(get_class($source));

            foreach ($properties as $property) {
                if ($property->isInitialized($source)) {
                    $result[$property->getName()] = $property->getValue($source);
                }
            }

            return $result;
        } catch (Throwable $e) {
            throw MappingException::normalizationFailed($source, 'public properties', $e);
        }
    }

    /**
     * @param callable(): mixed $adapter
     */
    /** @return array<array-key, mixed> */
    private function normalizeWithAdapter(object $source, string $strategy, callable $adapter): array
    {
        try {
            $result = $adapter();

            if (is_array($result)) {
                return $result;
            }

            if ($result instanceof stdClass) {
                return (array) $result;
            }

            throw new UnexpectedValueException(sprintf(
                'Adapter must return array or stdClass, got %s',
                get_debug_type($result),
            ));
        } catch (Throwable $e) {
            throw MappingException::normalizationFailed($source, $strategy, $e);
        }
    }
}

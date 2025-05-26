<?php

namespace Ninja\Granite\Mapping\Core;

use Exception;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use stdClass;

/**
 * Normalizes source data to array format for consistent processing.
 */
final readonly class SourceNormalizer
{
    /**
     * @throws MappingException
     */
    public function normalize(mixed $source): array
    {
        return match (true) {
            is_array($source) => $source,
            $source instanceof GraniteObject => $source->array(),
            $source instanceof stdClass => (array) $source,
            is_object($source) => $this->objectToArray($source),
            default => throw MappingException::unsupportedSourceType($source)
        };
    }

    /**
     * @throws MappingException
     */
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
        } catch (Exception $e) {
            throw MappingException::unsupportedSourceType($source);
        }
    }
}
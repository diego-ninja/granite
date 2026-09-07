<?php
// ABOUTME: Determines whether a Granite object graph is safe to cache.
// ABOUTME: Rejects mutable containers and temporal values conservatively.

declare(strict_types=1);

namespace Ninja\Granite\Serialization;

use DateTimeImmutable;
use DateTimeInterface;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

final class SerializationCachePolicy
{
    /**
     * @var array<int, true>
     */
    private array $visited = [];

    public static function isCacheable(object $instance): bool
    {
        return (new self())->isCacheableObject($instance);
    }

    private function isCacheableObject(object $instance): bool
    {
        if ($instance instanceof DateTimeImmutable) {
            return true;
        }

        if ( ! $instance instanceof GraniteObject) {
            return false;
        }

        if ( ! ReflectionCache::getClass($instance::class)->isReadOnly()) {
            return false;
        }

        $objectId = spl_object_id($instance);
        if (isset($this->visited[$objectId])) {
            return true;
        }
        $this->visited[$objectId] = true;

        foreach (ReflectionCache::getPublicProperties($instance::class) as $property) {
            if ($this->declaresUnsupportedType($property->getType())) {
                return false;
            }

            if ( ! $property->isInitialized($instance)) {
                continue;
            }

            if ( ! $this->isCacheableValue($property->getValue($instance))) {
                return false;
            }
        }

        return true;
    }

    private function isCacheableValue(mixed $value): bool
    {
        if (null === $value || is_scalar($value) || $value instanceof UnitEnum) {
            return true;
        }

        if ($value instanceof DateTimeImmutable) {
            return true;
        }

        if ($value instanceof DateTimeInterface || is_array($value)) {
            return false;
        }

        return is_object($value) && $this->isCacheableObject($value);
    }

    private function declaresUnsupportedType(?ReflectionType $type): bool
    {
        if (null === $type) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if ($namedType instanceof ReflectionNamedType && 'mixed' === $namedType->getName()) {
                    return true;
                }
            }

            return false;
        }

        return ! $type instanceof ReflectionNamedType || 'mixed' === $type->getName();
    }
}

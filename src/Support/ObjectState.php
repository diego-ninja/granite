<?php
// ABOUTME: Extracts initialized public state using canonical PHP property names.
// ABOUTME: Keeps domain state independent from aliases, hidden fields, and serialized formats.

declare(strict_types=1);

namespace Ninja\Granite\Support;

final readonly class ObjectState
{
    /** @return array<string, mixed> */
    public static function extract(object $object): array
    {
        $state = [];

        foreach (ReflectionCache::getPublicProperties($object::class) as $property) {
            if ($property->isInitialized($object)) {
                $state[$property->getName()] = $property->getValue($object);
            }
        }

        return $state;
    }
}

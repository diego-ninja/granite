<?php

// ABOUTME: WeakMap-based cache for serialized array results.
// ABOUTME: Auto-cleans via GC since Granite objects are readonly/immutable.

namespace Ninja\Granite\Serialization;

use WeakMap;

final class SerializationCache
{
    /** @var WeakMap<object, array> */
    private static ?WeakMap $cache = null;

    public static function get(object $instance): ?array
    {
        if (null === self::$cache) {
            return null;
        }

        return self::$cache[$instance] ?? null;
    }

    public static function set(object $instance, array $result): void
    {
        self::$cache ??= new WeakMap();
        self::$cache[$instance] = $result;
    }
}

<?php

// ABOUTME: WeakMap-based cache for serialized array and JSON results.
// ABOUTME: Auto-cleans via GC since Granite objects are readonly/immutable.

namespace Ninja\Granite\Serialization;

use WeakMap;

final class SerializationCache
{
    /** @var WeakMap<object, array> */
    private static ?WeakMap $arrayCache = null;

    /** @var WeakMap<object, string> */
    private static ?WeakMap $jsonCache = null;

    public static function get(object $instance): ?array
    {
        if (null === self::$arrayCache) {
            return null;
        }

        return self::$arrayCache[$instance] ?? null;
    }

    public static function set(object $instance, array $result): void
    {
        self::$arrayCache ??= new WeakMap();
        self::$arrayCache[$instance] = $result;
    }

    public static function getJson(object $instance): ?string
    {
        if (null === self::$jsonCache) {
            return null;
        }

        return self::$jsonCache[$instance] ?? null;
    }

    public static function setJson(object $instance, string $result): void
    {
        self::$jsonCache ??= new WeakMap();
        self::$jsonCache[$instance] = $result;
    }
}

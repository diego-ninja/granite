<?php

// ABOUTME: WeakMap-based cache for serialized array and JSON results.
// ABOUTME: Auto-cleans via GC while allowing explicit resets for deterministic tests.

namespace Ninja\Granite\Serialization;

use WeakMap;

final class SerializationCache
{
    /** @var WeakMap<object, array<array-key, mixed>> */
    private static ?WeakMap $arrayCache = null;

    /** @var WeakMap<object, string> */
    private static ?WeakMap $jsonCache = null;

    /** @return array<array-key, mixed>|null */
    public static function get(object $instance): ?array
    {
        if (null === self::$arrayCache) {
            return null;
        }

        return self::$arrayCache[$instance] ?? null;
    }

    /** @param array<array-key, mixed> $result */
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

    public static function clear(): void
    {
        self::$arrayCache = null;
        self::$jsonCache = null;
    }
}

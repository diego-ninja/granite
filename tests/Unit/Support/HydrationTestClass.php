<?php

namespace Tests\Unit\Support;

use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Traits\HasDeserialization;
use ReflectionProperty;
use ReflectionType;

class HydrationTestClass
{
    use HasDeserialization;

    public string $noDefaultNonNullable;
    public string $defaultNonNullable = 'defaultNonNullable';
    public ?string $noDefaultNullable;
    public ?string $defaultNullable = 'defaultNullable';

    public static function testHydrate(array $data): static
    {
        $instance = new self();
        return self::hydrateInstance($instance, $data);
    }


    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function hasValueSetInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): bool {
        return array_key_exists($phpName, $data) ?? array_key_exists($serializedName, $data) ?? false;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

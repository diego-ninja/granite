<?php

namespace Tests\Unit\Support;

use DateTimeInterface;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Traits\HasDeserialization;
use Ninja\Granite\Traits\HasTypeConversion;
use ReflectionProperty;
use Tests\Data\StatusTestEnum;

class HydrationTestClass
{
    use HasDeserialization;
    use HasTypeConversion;

    public string $noDefaultNonNullable;
    public string $defaultNonNullable = 'defaultNonNullable';
    public ?string $noDefaultNullable;
    public ?string $defaultNullable = 'defaultNullable';

    public StatusTestEnum $requiredEnum;
    public StatusTestEnum $defaultEnum = StatusTestEnum::Paused;
    public ?StatusTestEnum $nullableEnum;
    public ?StatusTestEnum $defaultNullableEnum = StatusTestEnum::Paused;

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

    protected static function convertToCarbon(mixed $value, string $typeName, ?ReflectionProperty $property = null, ?DateTimeProvider $classProvider = null): ?DateTimeInterface
    {
        return null;
    }

    protected static function convertToDateTime(mixed $value, string $typeName, ?ReflectionProperty $property = null, ?DateTimeProvider $classProvider = null): ?DateTimeInterface
    {
        return null;
    }
}

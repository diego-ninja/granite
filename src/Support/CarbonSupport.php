<?php

namespace Ninja\Granite\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Exception;

/**
 * Carbon support detection and factory.
 * Provides conditional Carbon support when the library is available.
 */
final class CarbonSupport
{
    /**
     * Cache for Carbon availability check.
     */
    private static ?bool $isAvailable = null;

    /**
     * Cache for CarbonImmutable availability check.
     */
    private static ?bool $isImmutableAvailable = null;

    /**
     * Check if Carbon library is available.
     *
     * @return bool True if Carbon is available
     */
    public static function isAvailable(): bool
    {
        if (null === self::$isAvailable) {
            self::$isAvailable = class_exists('Carbon\Carbon');
        }

        return self::$isAvailable;
    }

    /**
     * Check if CarbonImmutable is available.
     *
     * @return bool True if CarbonImmutable is available
     */
    public static function isImmutableAvailable(): bool
    {
        if (null === self::$isImmutableAvailable) {
            self::$isImmutableAvailable = class_exists('Carbon\CarbonImmutable');
        }

        return self::$isImmutableAvailable;
    }

    /**
     * Check if a class name is Carbon.
     *
     * @param string $className Class name to check
     * @return bool True if it's Carbon
     */
    public static function isCarbon(string $className): bool
    {
        return self::isAvailable() && 'Carbon\Carbon' === $className;
    }

    /**
     * Check if a class name is CarbonImmutable.
     *
     * @param string $className Class name to check
     * @return bool True if it's CarbonImmutable
     */
    public static function isCarbonImmutable(string $className): bool
    {
        return self::isImmutableAvailable() && 'Carbon\CarbonImmutable' === $className;
    }

    /**
     * Check if a class name is any Carbon variant.
     *
     * @param string $className Class name to check
     * @return bool True if it's any Carbon class
     */
    public static function isCarbonClass(string $className): bool
    {
        return self::isCarbon($className) || self::isCarbonImmutable($className);
    }

    /**
     * Check if a value is a Carbon instance.
     *
     * @param mixed $value Value to check
     * @return bool True if it's a Carbon instance
     */
    public static function isCarbonInstance(mixed $value): bool
    {
        if ( ! self::isAvailable()) {
            return false;
        }

        return $value instanceof Carbon ||
            (self::isImmutableAvailable() && $value instanceof CarbonImmutable);
    }

    /**
     * Create a Carbon instance from various input types.
     *
     * @param mixed $value Input value (string, timestamp, etc.)
     * @param string|null $format Optional format for parsing
     * @param string|null $timezone Optional timezone
     * @param bool $immutable Whether to create CarbonImmutable
     * @return DateTimeInterface|null Carbon instance or null on failure
     */
    public static function create(
        mixed $value,
        ?string $format = null,
        ?string $timezone = null,
        bool $immutable = false,
    ): ?DateTimeInterface {
        if ( ! self::isAvailable()) {
            return null;
        }

        if ($immutable && ! self::isImmutableAvailable()) {
            return null;
        }

        try {
            $carbonClass = $immutable ? 'Carbon\CarbonImmutable' : 'Carbon\Carbon';

            // Handle null values
            if (null === $value) {
                return null;
            }

            // If already a Carbon instance, return as-is or convert
            if (self::isCarbonInstance($value)) {
                if ($immutable && ! ($value instanceof CarbonImmutable)) {
                    /** @var Carbon $value */
                    return CarbonImmutable::instance($value);
                }
                if ( ! $immutable && ! ($value instanceof Carbon)) {
                    /** @var CarbonImmutable $value */
                    return Carbon::instance($value);
                }
                /** @var DateTimeInterface $value */
                return $value;
            }

            // If already a DateTimeInterface, convert to Carbon
            if ($value instanceof DateTimeInterface) {
                return $carbonClass::instance($value);
            }

            // Parse from string with optional format
            if (is_string($value)) {
                if (null !== $format) {
                    return $carbonClass::createFromFormat($format, $value, $timezone);
                }
                return $carbonClass::parse($value, $timezone);
            }

            // Parse from timestamp
            if (is_int($value) || is_float($value)) {
                return $carbonClass::createFromTimestamp($value, $timezone);
            }

            return null;

        } catch (Exception) {
            return null;
        }
    }

    /**
     * Create a Carbon instance.
     *
     * @param mixed $value Input value
     * @param string|null $format Optional format
     * @param string|null $timezone Optional timezone
     * @return DateTimeInterface|null Carbon instance
     */
    public static function createCarbon(
        mixed $value,
        ?string $format = null,
        ?string $timezone = null,
    ): ?DateTimeInterface {
        return self::create($value, $format, $timezone);
    }

    /**
     * Create a CarbonImmutable instance.
     *
     * @param mixed $value Input value
     * @param string|null $format Optional format
     * @param string|null $timezone Optional timezone
     * @return DateTimeInterface|null CarbonImmutable instance
     */
    public static function createImmutable(
        mixed $value,
        ?string $format = null,
        ?string $timezone = null,
    ): ?DateTimeInterface {
        return self::create($value, $format, $timezone, true);
    }

    /**
     * Get the preferred Carbon class name based on configuration.
     *
     * @param bool $preferImmutable Whether to prefer immutable
     * @return string|null Carbon class name or null if not available
     */
    public static function getPreferredCarbonClass(bool $preferImmutable = false): ?string
    {
        if ($preferImmutable && self::isImmutableAvailable()) {
            return 'Carbon\CarbonImmutable';
        }

        if (self::isAvailable()) {
            return 'Carbon\Carbon';
        }

        return null;
    }

    /**
     * Serialize a Carbon instance to string.
     *
     * @param DateTimeInterface $carbon Carbon instance
     * @param string|null $format Optional format (defaults to ISO 8601)
     * @param string|null $timezone Optional timezone for output
     * @return string Serialized date string
     */
    public static function serialize(
        DateTimeInterface $carbon,
        ?string $format = null,
        ?string $timezone = null,
    ): string {
        $format ??= DateTimeInterface::ATOM;

        // If timezone conversion is requested and it's a Carbon instance
        if (null !== $timezone && self::isCarbonInstance($carbon)) {
            if ($carbon instanceof Carbon) {
                $carbon = $carbon->setTimezone($timezone);
            } elseif ($carbon instanceof CarbonImmutable) {
                $carbon = $carbon->setTimezone($timezone);
            }
        }

        return $carbon->format($format);
    }

    /**
     * Reset availability cache (useful for testing).
     *
     * @return void
     */
    public static function resetCache(): void
    {
        self::$isAvailable = null;
        self::$isImmutableAvailable = null;
    }
}

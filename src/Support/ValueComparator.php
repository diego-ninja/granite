<?php
// ABOUTME: Defines ValueComparator as part of shared reflection, comparison and date support.
// ABOUTME: Owns the ValueComparator boundary within shared reflection, comparison and date support.

// ABOUTME: Compares Granite values using the same semantics across all execution paths.
// ABOUTME: Handles nested objects, temporal precision, arrays, enums, scalars, and fallback objects.

declare(strict_types=1);

namespace Ninja\Granite\Support;

use BackedEnum;
use DateTimeInterface;
use Ninja\Granite\Granite;
use UnitEnum;

final class ValueComparator
{
    public static function equals(mixed $left, mixed $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if (null === $left || null === $right) {
            return false;
        }

        if ($left instanceof DateTimeInterface || $right instanceof DateTimeInterface) {
            return $left instanceof DateTimeInterface
                && $right instanceof DateTimeInterface
                && $left->format('U.u') === $right->format('U.u')
                && $left->getTimezone()->getName() === $right->getTimezone()->getName();
        }

        if ($left instanceof Granite || $right instanceof Granite) {
            return $left instanceof Granite
                && $right instanceof Granite
                && $left::class === $right::class
                && $left->equals($right);
        }

        if (is_array($left) || is_array($right)) {
            if ( ! is_array($left) || ! is_array($right) || count($left) !== count($right)) {
                return false;
            }

            foreach ($left as $key => $leftValue) {
                if ( ! array_key_exists($key, $right)
                    || ! self::equals($leftValue, $right[$key])) {
                    return false;
                }
            }

            return true;
        }

        if ($left instanceof UnitEnum || $right instanceof UnitEnum) {
            if ( ! $left instanceof UnitEnum
                || ! $right instanceof UnitEnum
                || $left::class !== $right::class) {
                return false;
            }

            if ($left instanceof BackedEnum && $right instanceof BackedEnum) {
                return $left->value === $right->value;
            }

            return $left->name === $right->name;
        }

        if (is_scalar($left) || is_scalar($right)) {
            return is_scalar($left)
                && is_scalar($right)
                && get_debug_type($left) === get_debug_type($right)
                && $left === $right;
        }

        if (is_object($left) || is_object($right)) {
            if ( ! is_object($left) || ! is_object($right) || $left::class !== $right::class) {
                return false;
            }

            if (method_exists($left, '__toString') && method_exists($right, '__toString')) {
                return (string) $left === (string) $right;
            }

            return serialize($left) === serialize($right);
        }

        return false;
    }
}

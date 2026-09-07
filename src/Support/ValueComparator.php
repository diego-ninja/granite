<?php
// ABOUTME: Compares Granite values using the same semantics across all execution paths.
// ABOUTME: Handles nested objects, temporal precision, arrays, enums, scalars, and fallback objects.

declare(strict_types=1);

namespace Ninja\Granite\Support;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Ninja\Granite\Granite;
use ReflectionClass;
use ReflectionReference;
use stdClass;
use UnitEnum;

final class ValueComparator
{
    public static function equals(mixed $left, mixed $right): bool
    {
        $context = [
            'leftObjects' => [],
            'rightObjects' => [],
            'leftReferences' => [],
            'rightReferences' => [],
        ];

        return self::compare($left, $right, $context);
    }

    /**
     * @param array{
     *     leftObjects: array<int, int>,
     *     rightObjects: array<int, int>,
     *     leftReferences: array<string, string>,
     *     rightReferences: array<string, string>
     * } $context
     */
    private static function compare(mixed $left, mixed $right, array &$context): bool
    {
        if ( ! is_array($left) && ! is_array($right) && $left === $right) {
            return true;
        }

        if (null === $left || null === $right) {
            return false;
        }

        if (is_resource($left) || is_resource($right)) {
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
                && self::compareObjects($left, $right, $context);
        }

        if (is_array($left) || is_array($right)) {
            return is_array($left)
                && is_array($right)
                && self::compareArrays($left, $right, $context);
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

            if ($left instanceof Closure || $right instanceof Closure) {
                return false;
            }

            return self::compareObjects($left, $right, $context);
        }

        return false;
    }

    /**
     * @param array<string|int, mixed> $left
     * @param array<string|int, mixed> $right
     * @param array{
     *     leftObjects: array<int, int>,
     *     rightObjects: array<int, int>,
     *     leftReferences: array<string, string>,
     *     rightReferences: array<string, string>
     * } $context
     */
    private static function compareArrays(array $left, array $right, array &$context): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $key => $leftValue) {
            if ( ! array_key_exists($key, $right)) {
                return false;
            }

            $leftReference = ReflectionReference::fromArrayElement($left, $key);
            $rightReference = ReflectionReference::fromArrayElement($right, $key);
            if ((null === $leftReference) !== (null === $rightReference)) {
                return false;
            }

            if (null !== $leftReference && null !== $rightReference) {
                $leftId = bin2hex($leftReference->getId());
                $rightId = bin2hex($rightReference->getId());
                if (isset($context['leftReferences'][$leftId])) {
                    if ($context['leftReferences'][$leftId] !== $rightId
                        || ($context['rightReferences'][$rightId] ?? null) !== $leftId) {
                        return false;
                    }

                    continue;
                }

                if (isset($context['rightReferences'][$rightId])) {
                    return false;
                }

                $context['leftReferences'][$leftId] = $rightId;
                $context['rightReferences'][$rightId] = $leftId;
            }

            if ( ! self::compare($leftValue, $right[$key], $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{
     *     leftObjects: array<int, int>,
     *     rightObjects: array<int, int>,
     *     leftReferences: array<string, string>,
     *     rightReferences: array<string, string>
     * } $context
     */
    private static function compareObjects(object $left, object $right, array &$context): bool
    {
        $leftId = spl_object_id($left);
        $rightId = spl_object_id($right);
        if (isset($context['leftObjects'][$leftId])) {
            return $context['leftObjects'][$leftId] === $rightId
                && ($context['rightObjects'][$rightId] ?? null) === $leftId;
        }

        if (isset($context['rightObjects'][$rightId])) {
            return false;
        }

        $context['leftObjects'][$leftId] = $rightId;
        $context['rightObjects'][$rightId] = $leftId;

        if ($left instanceof Granite && $right instanceof Granite) {
            return self::compare(ObjectState::extract($left), ObjectState::extract($right), $context);
        }

        if ($left instanceof stdClass && $right instanceof stdClass) {
            return self::compare((array) $left, (array) $right, $context);
        }

        if ((new ReflectionClass($left))->isInternal()) {
            return false;
        }

        return self::compare((array) $left, (array) $right, $context);
    }
}

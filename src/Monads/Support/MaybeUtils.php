<?php

namespace Ninja\Granite\Monads\Support;

use InvalidArgumentException;
use Ninja\Granite\Monads\Contracts\Maybe;
use Ninja\Granite\Monads\Factories\Maybe;

class MaybeUtils
{
    /**
     * Combine multiple Maybe values - all must be Some for result to be Some
     */
    public static function combine(array $maybes): Maybe
    {
        $values = [];

        foreach ($maybes as $maybe) {
            if (!$maybe instanceof Maybe) {
                throw new InvalidArgumentException("All items must be Maybe instances");
            }

            if ($maybe->isNone()) {
                return Maybe::none();
            }

            $values[] = $maybe->get();
        }

        return Maybe::some($values);
    }

    /**
     * Find first non-empty Maybe
     */
    public static function firstPresent(array $maybes): Maybe
    {
        foreach ($maybes as $maybe) {
            if (!$maybe instanceof Maybe) {
                throw new InvalidArgumentException("All items must be Maybe instances");
            }

            if ($maybe->isSome()) {
                return $maybe;
            }
        }

        return Maybe::none();
    }

    /**
     * Transform array of values to array of Maybes
     */
    public static function fromArray(array $values): array
    {
        return array_map(fn($value) => Maybe::of($value), $values);
    }

    /**
     * Extract values from array of Maybes, filtering out None values
     */
    public static function extractPresent(array $maybes): array
    {
        return array_map(
            fn($maybe) => $maybe->get(),
            array_filter($maybes, fn($maybe) => $maybe->isSome())
        );
    }

    /**
     * Sequence operation - converts an array of Maybe<T> to Maybe<array<T>>
     */
    public static function sequence(array $maybes): Maybe
    {
        return self::combine($maybes);
    }

    /**
     * Traverse operation - map function over array and sequence the results
     */
    public static function traverse(array $values, callable $mapper): Maybe
    {
        $maybes = array_map($mapper, $values);
        return self::sequence($maybes);
    }
}
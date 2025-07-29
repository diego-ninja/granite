<?php

namespace Ninja\Granite\Monads\Factories;

use Ninja\Granite\Monads\Contracts\Maybe as MaybeContract;
use Ninja\Granite\Monads\None;
use Ninja\Granite\Monads\Some;
use Throwable;

final readonly class Maybe
{
    /**
     * Create a Maybe with a value (Some)
     */
    public static function some(mixed $value): MaybeContract
    {
        if ($value === null) {
            throw new \InvalidArgumentException("Cannot create Some with null value. Use of() or none() instead.");
        }
        return new Some($value);
    }

    /**
     * Create an empty Maybe (None)
     */
    public static function none(): MaybeContract
    {
        return None::instance();
    }

    /**
     * Create Maybe from a potentially null value
     */
    public static function of(mixed $value): MaybeContract
    {
        return $value === null ? self::none() : new Some($value);
    }

    /**
     * Create Maybe from a callable that might throw
     */
    public static function fromCallable(callable $supplier): MaybeContract
    {
        try {
            $result = $supplier();
            return self::of($result);
        } catch (Throwable) {
            return self::none();
        }
    }
}
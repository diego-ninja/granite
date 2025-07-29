<?php

namespace Ninja\Granite\Monads\Factories;

use Ninja\Granite\Monads\Left;
use Ninja\Granite\Monads\Right;
use Ninja\Granite\Monads\Contracts\Either as EitherContract;

final readonly class Either
{
    public static function left(mixed $value): EitherContract { return new Left($value); }
    public static function right(mixed $value): EitherContract { return new Right($value); }

    public static function fromCallable(callable $fn): EitherContract
    {
        try {
            return self::right($fn());
        } catch (\Throwable $e) {
            return self::left($e);
        }
    }
}
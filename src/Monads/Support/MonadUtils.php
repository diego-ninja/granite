<?php

namespace Ninja\Granite\Monads\Support;

use Ninja\Granite\Monads\Contracts\Either as EitherContract;
use Ninja\Granite\Monads\Factories\Either;
use Ninja\Granite\Monads\IO;

final class MonadUtils
{
    /**
     * Sequence operations for arrays of monads
     */
    public static function sequenceEither(array $eithers): EitherContract
    {
        $values = [];
        foreach ($eithers as $either) {
            if ($either->isLeft()) {
                return $either;
            }
            $values[] = $either->getRight();
        }
        return Either::right($values);
    }

    public static function sequenceIO(array $ios): IO
    {
        return new IO(function() use ($ios) {
            $results = [];
            foreach ($ios as $io) {
                $results[] = $io->run();
            }
            return $results;
        });
    }

    /**
     * Lift regular functions to work with monads
     */
    public static function liftEither(callable $function): callable
    {
        return fn(EitherContract $either) => $either->map($function);
    }

    public static function liftIO(callable $function): callable
    {
        return fn(IO $io) => $io->map($function);
    }
}
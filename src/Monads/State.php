<?php

namespace Ninja\Granite\Monads;

final readonly class State
{
    public function __construct(private \Closure $computation) {}

    public function run(mixed $initialState): array
    {
        return ($this->computation)($initialState);
    }

    public function getValue(mixed $initialState): mixed
    {
        return $this->run($initialState)[0];
    }

    public function getState(mixed $initialState): mixed
    {
        return $this->run($initialState)[1];
    }

    public function map(callable $mapper): self
    {
        return new self(function($state) use ($mapper) {
            [$value, $newState] = $this->run($state);
            return [$mapper($value), $newState];
        });
    }

    public function flatMap(callable $mapper): self
    {
        return new self(function($state) use ($mapper) {
            [$value, $newState] = $this->run($state);
            $nextComputation = $mapper($value);
            return $nextComputation instanceof self
                ? $nextComputation->run($newState)
                : [$nextComputation, $newState];
        });
    }

    public static function of(mixed $value): self
    {
        return new self(fn($state) => [$value, $state]);
    }

    public static function get(): self
    {
        return new self(fn($state) => [$state, $state]);
    }

    public static function put(mixed $newState): self
    {
        return new self(fn($state) => [null, $newState]);
    }

    public static function modify(callable $modifier): self
    {
        return new self(fn($state) => [null, $modifier($state)]);
    }
}
<?php

namespace Ninja\Granite\Monads;

final readonly class IO
{
    public function __construct(private \Closure $action) {}

    public function run(): mixed
    {
        return ($this->action)();
    }

    public function map(callable $mapper): self
    {
        return new self(fn() => $mapper($this->run()));
    }

    public function flatMap(callable $mapper): self
    {
        return new self(function() use ($mapper) {
            $result = $mapper($this->run());
            return $result instanceof self ? $result->run() : $result;
        });
    }

    public function andThen(self $other): self
    {
        return new self(function() use ($other) {
            $this->run();
            return $other->run();
        });
    }

    public function bracket(callable $acquire, callable $release): self
    {
        return new self(function() use ($acquire, $release) {
            $resource = $acquire();
            try {
                return $this->run();
            } finally {
                $release($resource);
            }
        });
    }

    public static function of(mixed $value): self
    {
        return new self(fn() => $value);
    }

    public static function from(callable $action): self
    {
        return new self($action);
    }

    public static function delay(callable $action): self
    {
        return new self($action);
    }
}
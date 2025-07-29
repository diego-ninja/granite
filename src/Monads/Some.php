<?php

namespace Ninja\Granite\Monads;

use Ninja\Granite\Monads\Contracts\Maybe;

final readonly class Some implements Maybe
{
    public function __construct(private mixed $value)
    {
        // Note: Some can contain any value except null
        // The null check should be done in the factory
    }

    public function isSome(): bool
    {
        return true;
    }

    public function isNone(): bool
    {
        return false;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function getOrElse(mixed $default): mixed
    {
        return $this->value;
    }

    public function getOrElseGet(callable $supplier): mixed
    {
        return $this->value;
    }

    public function map(callable $mapper): Maybe
    {
        try {
            $result = $mapper($this->value);
            return Maybe::of($result);
        } catch (\Throwable) {
            return Maybe::none();
        }
    }

    public function flatMap(callable $mapper): Maybe
    {
        try {
            $result = $mapper($this->value);

            if (!$result instanceof Maybe) {
                throw new \InvalidArgumentException("flatMap mapper must return a Maybe instance");
            }

            return $result;
        } catch (\Throwable) {
            return Maybe::none();
        }
    }

    public function filter(callable $predicate): Maybe
    {
        try {
            return $predicate($this->value) ? $this : Maybe::none();
        } catch (\Throwable) {
            return Maybe::none();
        }
    }

    public function ifPresent(callable $action): Maybe
    {
        try {
            $action($this->value);
        } catch (\Throwable) {
            // Ignore exceptions in side effects
        }
        return $this;
    }

    public function ifAbsent(callable $action): Maybe
    {
        return $this;
    }

    public function orElse(Maybe $alternative): Maybe
    {
        return $this;
    }

    public function __toString(): string
    {
        return "Some(" . var_export($this->value, true) . ")";
    }
}
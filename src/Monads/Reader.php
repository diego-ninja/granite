<?php

namespace Ninja\Granite\Monads;

use Closure;

final readonly class Reader
{
    public function __construct(private Closure $computation) {}

    public function run(mixed $context): mixed
    {
        return ($this->computation)($context);
    }

    public function map(callable $mapper): self
    {
        return new self(fn($context) => $mapper($this->run($context)));
    }

    public function flatMap(callable $mapper): self
    {
        return new self(function($context) use ($mapper) {
            $result = $mapper($this->run($context));
            return $result instanceof self ? $result->run($context) : $result;
        });
    }

    public function local(callable $transformer): self
    {
        return new self(fn($context) => $this->run($transformer($context)));
    }

    public static function of(mixed $value): self
    {
        return new self(fn($context) => $value);
    }

    public static function ask(): self
    {
        return new self(fn($context) => $context);
    }

    public static function asks(callable $selector): self
    {
        return new self(fn($context) => $selector($context));
    }
}
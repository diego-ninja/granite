<?php

namespace Ninja\Granite\Monads;

use Ninja\Granite\Monads\Contracts\Either;

final readonly class Left implements Either
{
    public function __construct(private mixed $value) {}

    public function isLeft(): bool { return true; }
    public function isRight(): bool { return false; }
    public function getLeft(): mixed { return $this->value; }
    public function getRight(): mixed { throw new \RuntimeException("Cannot get right value from Left"); }

    public function map(callable $mapper): Either { return $this; }
    public function flatMap(callable $mapper): Either { return $this; }

    public function mapLeft(callable $mapper): Either
    {
        return new self($mapper($this->value));
    }

    public function fold(callable $leftMapper, callable $rightMapper): mixed
    {
        return $leftMapper($this->value);
    }
}
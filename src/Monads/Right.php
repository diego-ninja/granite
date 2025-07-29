<?php

namespace Ninja\Granite\Monads;

use Ninja\Granite\Monads\Contracts\Either;

final readonly class Right implements Either
{
    public function __construct(private mixed $value) {}

    public function isLeft(): bool { return false; }
    public function isRight(): bool { return true; }
    public function getLeft(): mixed { throw new \RuntimeException("Cannot get left value from Right"); }
    public function getRight(): mixed { return $this->value; }

    public function map(callable $mapper): Either
    {
        try {
            return new self($mapper($this->value));
        } catch (\Throwable $e) {
            return new Left($e);
        }
    }

    public function flatMap(callable $mapper): Either
    {
        try {
            $result = $mapper($this->value);
            return $result instanceof Either ? $result : new self($result);
        } catch (\Throwable $e) {
            return new Left($e);
        }
    }

    public function mapLeft(callable $mapper): Either { return $this; }

    public function fold(callable $leftMapper, callable $rightMapper): mixed
    {
        return $rightMapper($this->value);
    }
}
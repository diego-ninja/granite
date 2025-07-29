<?php

namespace Ninja\Granite\Monads;

final readonly class Identity
{
    public function __construct(private mixed $value) {}

    public function get(): mixed { return $this->value; }

    public function map(callable $mapper): self
    {
        return new self($mapper($this->value));
    }

    public function flatMap(callable $mapper): self
    {
        $result = $mapper($this->value);
        return $result instanceof self ? $result : new self($result);
    }

    public function apply(callable $function): self
    {
        return $this->map($function);
    }

    public static function of(mixed $value): self
    {
        return new self($value);
    }
}
<?php

namespace Ninja\Granite\Monads;

final readonly class Pair
{
    public function __construct(private mixed $first, private mixed $second) {}

    public function first(): mixed { return $this->first; }
    public function second(): mixed { return $this->second; }

    public function map(callable $mapper): self
    {
        return new self($mapper($this->first), $this->second);
    }

    public function mapSecond(callable $mapper): self
    {
        return new self($this->first, $mapper($this->second));
    }

    public function mapBoth(callable $firstMapper, callable $secondMapper): self
    {
        return new self($firstMapper($this->first), $secondMapper($this->second));
    }

    public function flatMap(callable $mapper): self
    {
        $result = $mapper($this->first);
        return $result instanceof self
            ? new self($result->first(), $this->second)
            : new self($result, $this->second);
    }

    public function swap(): self { return new self($this->second, $this->first); }

    public function fold(callable $combiner): mixed
    {
        return $combiner($this->first, $this->second);
    }

    public function toArray(): array { return [$this->first, $this->second]; }

    public static function of(mixed $first, mixed $second): self
    {
        return new self($first, $second);
    }
}
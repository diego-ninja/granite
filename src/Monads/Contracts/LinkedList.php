<?php

namespace Ninja\Granite\Monads\Contracts;

interface LinkedList
{
    public function isEmpty(): bool;
    public function head(): mixed;
    public function tail(): self;
    public function cons(mixed $value): self;
    public function map(callable $mapper): self;
    public function flatMap(callable $mapper): self;
    public function filter(callable $predicate): self;
    public function fold(mixed $initial, callable $accumulator): mixed;
    public function toArray(): array;
}
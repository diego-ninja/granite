<?php

namespace Ninja\Granite\Monads;

use Ninja\Granite\Monads\Contracts\LinkedList as LinkedListContract;
use Ninja\Granite\Monads\Factories\LinkedList;

final readonly class Cons implements LinkedListContract
{
    public function __construct(private mixed $head, private LinkedListContract $tail) {}

    public function isEmpty(): bool { return false; }
    public function head(): mixed { return $this->head; }
    public function tail(): LinkedListContract { return $this->tail; }

    public function cons(mixed $value): LinkedListContract { return new self($value, $this); }

    public function map(callable $mapper): LinkedListContract
    {
        return new self($mapper($this->head), $this->tail->map($mapper));
    }

    public function flatMap(callable $mapper): LinkedListContract
    {
        $mapped = $mapper($this->head);
        $list = $mapped instanceof LinkedListContract ? $mapped : LinkedList::fromArray([$mapped]);
        return $this->concat($list, $this->tail->flatMap($mapper));
    }

    public function filter(callable $predicate): LinkedListContract
    {
        $filteredTail = $this->tail->filter($predicate);
        return $predicate($this->head) ? new self($this->head, $filteredTail) : $filteredTail;
    }

    public function fold(mixed $initial, callable $accumulator): mixed
    {
        return $this->tail->fold($accumulator($initial, $this->head), $accumulator);
    }

    public function toArray(): array
    {
        return [$this->head, ...$this->tail->toArray()];
    }

    private function concat(LinkedListContract $list1, LinkedListContract $list2): LinkedListContract
    {
        if ($list1->isEmpty()) return $list2;
        return new self($list1->head(), $this->concat($list1->tail(), $list2));
    }
}
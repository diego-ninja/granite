<?php

namespace Ninja\Granite\Monads;

use Ninja\Granite\Monads\Contracts\LinkedList;

final class Nil implements LinkedList
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isEmpty(): bool { return true; }
    public function head(): mixed { throw new \RuntimeException("Cannot get head of empty list"); }
    public function tail(): LinkedList { throw new \RuntimeException("Cannot get tail of empty list"); }

    public function cons(mixed $value): LinkedList { return new Cons($value, $this); }
    public function map(callable $mapper): LinkedList { return $this; }
    public function flatMap(callable $mapper): LinkedList { return $this; }
    public function filter(callable $predicate): LinkedList { return $this; }

    public function fold(mixed $initial, callable $accumulator): mixed { return $initial; }
    public function toArray(): array { return []; }
}
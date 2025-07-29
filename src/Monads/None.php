<?php

namespace Ninja\Granite\Monads;

use Ninja\Granite\Monads\Contracts\Maybe;

final class None implements Maybe
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

    public function isSome(): bool
    {
        return false;
    }

    public function isNone(): bool
    {
        return true;
    }

    public function get(): mixed
    {
        throw new \RuntimeException("Cannot get value from None");
    }

    public function getOrElse(mixed $default): mixed
    {
        return $default;
    }

    public function getOrElseGet(callable $supplier): mixed
    {
        try {
            return $supplier();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Supplier function failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function map(callable $mapper): Maybe
    {
        return $this;
    }

    public function flatMap(callable $mapper): Maybe
    {
        return $this;
    }

    public function filter(callable $predicate): Maybe
    {
        return $this;
    }

    public function ifPresent(callable $action): Maybe
    {
        return $this;
    }

    public function ifAbsent(callable $action): Maybe
    {
        try {
            $action();
        } catch (\Throwable) {
            // Ignore exceptions in side effects
        }
        return $this;
    }

    public function orElse(Maybe $alternative): Maybe
    {
        return $alternative;
    }

    public function __toString(): string
    {
        return "None";
    }
}
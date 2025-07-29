<?php

namespace Ninja\Granite\Monads\Contracts;

/**
 * Base interface for Maybe/Option monad
 */
interface Maybe
{
    /**
     * Check if this Maybe contains a value
     */
    public function isSome(): bool;

    /**
     * Check if this Maybe is empty
     */
    public function isNone(): bool;

    /**
     * Get the value if present, throw exception if None
     */
    public function get(): mixed;

    /**
     * Get the value or return default
     */
    public function getOrElse(mixed $default): mixed;

    /**
     * Get the value or call supplier function
     */
    public function getOrElseGet(callable $supplier): mixed;

    /**
     * Transform the value if present
     */
    public function map(callable $mapper): self;

    /**
     * Flat map - for chaining Maybe operations
     */
    public function flatMap(callable $mapper): self;

    /**
     * Filter the value based on predicate
     */
    public function filter(callable $predicate): self;

    /**
     * Execute action if value is present
     */
    public function ifPresent(callable $action): self;

    /**
     * Execute action if value is absent
     */
    public function ifAbsent(callable $action): self;

    /**
     * Return alternative Maybe if this one is None
     */
    public function orElse(self $alternative): self;
}
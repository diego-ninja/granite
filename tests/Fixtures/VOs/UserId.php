<?php

// ABOUTME: Test fixture for UUID/ULID hydration with from() method only
// ABOUTME: Tests mixed-type factory method with string/int coercion

namespace Tests\Fixtures\VOs;

use InvalidArgumentException;

readonly class UserId
{
    private function __construct(
        public string $value
    ) {
    }

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return new self((string) $value);
        }

        throw new InvalidArgumentException('UserId requires string or int value');
    }

    public function toString(): string
    {
        return $this->value;
    }
}

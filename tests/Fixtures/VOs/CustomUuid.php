<?php

// ABOUTME: Test fixture for UUID/ULID hydration with both from() and fromString() methods
// ABOUTME: Used to verify factory method precedence when multiple methods exist

namespace Tests\Fixtures\VOs;

use InvalidArgumentException;

readonly class CustomUuid
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

        if (is_string($value)) {
            return new self($value);
        }

        throw new InvalidArgumentException('CustomUuid requires a string value');
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

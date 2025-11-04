<?php

// ABOUTME: Test fixture for UUID/ULID hydration with fromString() method only
// ABOUTME: Validates string-only factory method detection and empty value handling

namespace Tests\Fixtures\VOs;

use InvalidArgumentException;

readonly class Rcuid
{
    private function __construct(
        public string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Rcuid cannot be empty');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}

<?php

// ABOUTME: Test fixture for UUID/ULID hydration error handling
// ABOUTME: Both factory methods throw exceptions to test graceful failure scenarios

namespace Tests\Fixtures\VOs;

use RuntimeException;

readonly class InvalidId
{
    private function __construct(
        public string $value
    ) {
    }

    public static function from(mixed $value): self
    {
        throw new RuntimeException('from() always fails');
    }

    public static function fromString(string $value): self
    {
        throw new RuntimeException('fromString() always fails');
    }
}

<?php

// ABOUTME: Fixture for testing nested fast path - a DTO with a Granite-typed param.
// ABOUTME: No attributes, no conventions - pure primitives + one nested Granite object.

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\Granite;

final readonly class TeamDTO extends Granite
{
    public function __construct(
        public string $name,
        public PersonDTO $leader,
        public int $size,
    ) {}
}

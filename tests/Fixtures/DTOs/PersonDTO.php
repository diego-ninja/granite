<?php

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\Granite;

final readonly class PersonDTO extends Granite
{
    public function __construct(
        public string $name,
        public int $age,
        public string $email,
    ) {}

    // No need to override from() - it works transparently!
}

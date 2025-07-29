<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

class UserEntity
{
    public function __construct(
        public int $id,
        public ?string $firstName,
        public ?string $lastName,
        public string $emailAddress,
    ) {}
}

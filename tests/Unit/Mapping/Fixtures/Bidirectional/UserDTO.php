<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

class UserDTO
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $email,
    ) {}
}

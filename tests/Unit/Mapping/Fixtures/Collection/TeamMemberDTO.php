<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class TeamMemberDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
    ) {}
}

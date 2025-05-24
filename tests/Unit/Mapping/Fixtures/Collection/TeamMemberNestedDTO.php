<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class TeamMemberNestedDTO
{
    public function __construct(
        public string $name,
        public string $role
    ) {}
}

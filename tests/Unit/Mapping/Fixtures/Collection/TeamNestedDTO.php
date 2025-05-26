<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class TeamNestedDTO
{
    public function __construct(
        public string $name,
        public array $members = []
    ) {}
}

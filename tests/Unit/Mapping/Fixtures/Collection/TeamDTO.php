<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class TeamDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public array $members = []
    ) {}
}

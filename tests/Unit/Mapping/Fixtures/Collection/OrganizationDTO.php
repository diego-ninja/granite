<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class OrganizationDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public array $departments = [],
    ) {}
}

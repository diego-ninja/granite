<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class DepartmentDTO
{
    public function __construct(
        public string $name,
        public array $teams = []
    ) {}
}

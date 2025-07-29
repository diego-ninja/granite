<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class ProjectDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public array $tags = [],
    ) {}
}

<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class ConfigDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public array $settings = []
    ) {}
}

<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class ProjectDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public array $tags = [],
        public array $settings = []
    ) {}
}

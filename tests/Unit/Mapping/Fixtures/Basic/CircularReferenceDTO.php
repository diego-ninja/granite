<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class CircularReferenceDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?self $parent = null,
    ) {}
}

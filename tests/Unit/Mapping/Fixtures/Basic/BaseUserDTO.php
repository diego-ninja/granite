<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

readonly class BaseUserDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

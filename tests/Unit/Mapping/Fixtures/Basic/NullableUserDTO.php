<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class NullableUserDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $email = null,
    ) {}
}

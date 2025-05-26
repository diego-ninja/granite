<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class ReadonlyUserDTO extends GraniteDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email
    ) {}
}

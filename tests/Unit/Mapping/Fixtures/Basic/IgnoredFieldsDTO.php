<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\Ignore;

final readonly class IgnoredFieldsDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        #[Ignore]
        public ?string $password = null,
    ) {}
}

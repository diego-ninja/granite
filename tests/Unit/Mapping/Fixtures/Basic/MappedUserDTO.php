<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;

final readonly class MappedUserDTO extends GraniteDTO
{
    public function __construct(
        #[MapFrom('user_id')]
        public int $id,
        #[MapFrom('full_name')]
        public string $name,
        #[MapFrom('email_address')]
        public string $email,
    ) {}
}

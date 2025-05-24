<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;

final readonly class UserResponseDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        
        #[MapFrom('name')]
        public string $displayName,
        
        public string $email
    ) {}
}

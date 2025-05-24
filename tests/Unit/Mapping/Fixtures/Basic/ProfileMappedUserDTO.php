<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class ProfileMappedUserDTO extends GraniteDTO
{
    public function __construct(
        public ?string $fullName = null,
        public ?string $birthYear = null,
        public ?string $email = null
    ) {}
}

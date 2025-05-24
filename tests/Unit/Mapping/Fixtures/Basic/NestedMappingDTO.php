<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;

final readonly class NestedMappingDTO extends GraniteDTO
{
    public function __construct(
        #[MapFrom('user.personal.firstName')]
        public ?string $firstName = null,

        #[MapFrom('user.personal.lastName')]
        public ?string $lastName = null,

        #[MapFrom('user.contact.email')]
        public ?string $email = null,

        #[MapFrom('user.contact.phone')]
        public ?string $phone = null,

        #[MapFrom('metadata.createdAt')]
        public ?string $createdAt = null
    ) {}
}

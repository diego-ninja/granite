<?php

namespace Tests\Fixtures\Automapper\DTO;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;

final readonly class NestedMappingDTO extends GraniteDTO
{
    #[MapFrom('user.name')]
    public string $userName;

    #[MapFrom('user.profile.email')]
    public string $userEmail;

    public function __construct(string $userName, string $userEmail)
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
    }
}
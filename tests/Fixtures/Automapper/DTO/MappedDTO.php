<?php

namespace Tests\Fixtures\Automapper\DTO;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;

final readonly class MappedDTO extends GraniteDTO
{
    #[MapFrom('firstName')]
    public string $first_name;

    #[MapFrom('lastName')]
    public string $last_name;

    #[MapFrom('emailAddress')]
    public string $email;
}
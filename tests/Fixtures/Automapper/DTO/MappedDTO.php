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

    public function __construct(string $first_name, string $last_name, string $email)
    {
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
    }
}
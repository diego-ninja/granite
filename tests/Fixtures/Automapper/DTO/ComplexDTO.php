<?php

namespace Tests\Fixtures\Automapper\DTO;

use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\DTOs\UserDTO;

final readonly class ComplexDTO extends GraniteDTO
{
    public int $id;
    public UserDTO $user;
}
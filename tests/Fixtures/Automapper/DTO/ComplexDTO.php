<?php

namespace Tests\Fixtures\Automapper\DTO;

use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\DTOs\UserDTO;

final readonly class ComplexDTO extends GraniteDTO
{
    public int $id;
    public UserDTO $user;

    public function __construct(int $id, UserDTO $user)
    {
        $this->id = $id;
        $this->user = $user;
    }
}
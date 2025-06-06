<?php

namespace Tests\Fixtures\Automapper\DTO;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\Ignore;

final readonly class IgnoreDTO extends GraniteDTO
{
    public int $id;
    public string $name;
    public string $email;

    #[Ignore]
    public string $password;

    public function __construct(int $id, string $name, string $email, string $password)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
    }
}
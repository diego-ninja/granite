<?php
// tests/Fixtures/DTOs/UserDTO.php

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;
use Ninja\Granite\GraniteDTO;

final readonly class UserDTO extends GraniteDTO
{
    public function __construct(
        public int                $id,
        public string             $name,
        public string             $email,
        public ?DateTimeInterface $createdAt = null
    ) {}
}
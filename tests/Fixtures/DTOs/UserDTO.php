<?php
// tests/Fixtures/DTOs/UserDTO.php

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;
use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\VOs\Address;

final readonly class UserDTO extends GraniteDTO
{
    public function __construct(
        public int                $id,
        public string             $name,
        public string             $email,
        public Address  $address,
        public ?DateTimeInterface $createdAt = null
    ) {}
}
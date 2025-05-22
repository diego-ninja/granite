<?php

namespace Tests\Fixtures\DTOs;

final readonly class PlainDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email
    ) {}
}
<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class TestSnakeCaseDto extends GraniteDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $emailAddress,
    ) {}
}

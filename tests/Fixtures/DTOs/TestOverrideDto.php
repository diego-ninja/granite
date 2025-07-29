<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\SerializedName;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class TestOverrideDto extends GraniteDTO
{
    public function __construct(
        public string $firstName,
        #[SerializedName('custom_last')]
        public string $lastName,
        public string $emailAddress
    ) {}
}
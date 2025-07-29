<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\Hidden;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class TestHiddenDto extends GraniteDTO
{
    public function __construct(
        public string $publicField,
        #[Hidden]
        public string $secretField
    ) {}
}
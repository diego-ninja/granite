<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;

#[SerializationConvention(SnakeCaseConvention::class, bidirectional: false)]
final readonly class TestUnidirectionalDto extends GraniteDTO
{
    public function __construct(
        public string $testField,
    ) {}
}

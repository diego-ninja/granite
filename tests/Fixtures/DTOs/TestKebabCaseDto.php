<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Conventions\KebabCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;

#[SerializationConvention(KebabCaseConvention::class)]
final readonly class TestKebabCaseDto extends GraniteDTO
{
    public function __construct(
        public string $productName,
        public float $unitPrice,
        public bool $isAvailable
    ) {}
}
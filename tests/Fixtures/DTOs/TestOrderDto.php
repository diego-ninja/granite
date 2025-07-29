<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class TestOrderDto extends GraniteDTO
{
    public function __construct(
        public string $orderNumber,
        public TestSnakeCaseDto $customerInfo,
        public float $totalAmount
    ) {}
}
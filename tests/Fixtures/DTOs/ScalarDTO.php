<?php

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;

final readonly class ScalarDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public bool $active,
        public array $tags
    ) {}
}
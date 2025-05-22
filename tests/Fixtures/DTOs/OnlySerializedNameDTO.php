<?php

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class OnlySerializedNameDTO
{
    public function __construct(
        #[SerializedName('full_name')]
        public string $name,

        public string $description
    ) {}
}
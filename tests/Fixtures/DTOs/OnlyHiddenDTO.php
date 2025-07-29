<?php

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class OnlyHiddenDTO
{
    public function __construct(
        public string $name,
        #[Hidden]
        public string $secret,
    ) {}
}

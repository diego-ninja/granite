<?php

// tests/Fixtures/DTOs/SimpleDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;

final readonly class SimpleDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?int $age = null,
    ) {}
}

<?php

// tests/Fixtures/DTOs/NestedDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class NestedDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public ?UserDTO $author = null,
        public array $tags = [],
        #[Hidden]
        public ?string $internalNotes = null,
    ) {}
}

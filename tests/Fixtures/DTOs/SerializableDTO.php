<?php

// tests/Fixtures/DTOs/SerializableDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class SerializableDTO extends GraniteDTO
{
    public function __construct(
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[SerializedName('last_name')]
        public string $lastName,
        public string $email,
        #[Hidden]
        public string $password,
        #[Hidden]
        public ?string $apiToken = null,
    ) {}
}

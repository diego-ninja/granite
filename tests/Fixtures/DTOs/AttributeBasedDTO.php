<?php

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class AttributeBasedDTO
{
    public function __construct(
        #[SerializedName('first_name')]
        public string $firstName,
        #[SerializedName('last_name')]
        public string $lastName,
        public string $email,
        #[Hidden]
        public string $password,
        #[Hidden]
        public ?string $apiToken = null,
    ) {}
}

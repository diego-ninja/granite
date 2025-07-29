<?php

// tests/Fixtures/DTOs/PerformanceDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;
use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class PerformanceDTO extends GraniteDTO
{
    public function __construct(
        #[SerializedName('id')]
        public int $identifier,
        #[SerializedName('name')]
        public string $displayName,
        #[SerializedName('email')]
        public string $emailAddress,
        #[SerializedName('phone')]
        public ?string $phoneNumber = null,
        #[SerializedName('address')]
        public ?array $homeAddress = null,
        #[SerializedName('created_at')]
        public ?DateTimeInterface $creationDate = null,
        #[SerializedName('updated_at')]
        public ?DateTimeInterface $lastModified = null,
        #[SerializedName('metadata')]
        public array $additionalData = [],
        #[Hidden]
        public ?string $password = null,
        #[Hidden]
        public ?string $apiKey = null,
        #[Hidden]
        public ?string $refreshToken = null,
        #[Hidden]
        public array $internalFlags = [],
    ) {}
}

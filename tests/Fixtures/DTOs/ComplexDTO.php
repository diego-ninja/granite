<?php

// tests/Fixtures/DTOs/ComplexDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;
use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\Enums\UserStatus;

final readonly class ComplexDTO extends GraniteDTO
{
    public function __construct(
        public int                $id,
        public string             $name,
        public ?DateTimeInterface $createdAt = null,
        public ?UserStatus        $status = null,
        public array              $metadata = [],
    ) {}
}

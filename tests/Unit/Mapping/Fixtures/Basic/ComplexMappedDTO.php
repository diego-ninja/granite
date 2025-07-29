<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class ComplexMappedDTO extends GraniteDTO
{
    public function __construct(
        public ?int $userId = null,
        public ?string $fullName = null,
        public ?array $contactInfo = null,
    ) {}
}

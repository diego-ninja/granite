<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use DateTimeImmutable;
use DateTimeInterface;
use Ninja\Granite\GraniteDTO;

final readonly class EventDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?DateTimeInterface $startDate = null,
        public ?DateTimeInterface $endDate = null
    ) {}
}

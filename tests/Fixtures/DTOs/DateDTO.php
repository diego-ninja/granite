<?php

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;
use Ninja\Granite\GraniteDTO;

final readonly class DateDTO extends GraniteDTO
{
    public function __construct(
        public string|DateTimeInterface|null $flexibleDate,
    ) {}
}

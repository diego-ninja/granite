<?php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\Enums\Color;
use Tests\Fixtures\Enums\Priority;

final readonly class BackedEnumDTO extends GraniteDTO
{
    public function __construct(
        public ?Priority $priority = null,
        public ?Color $color = null,
    ) {}
}

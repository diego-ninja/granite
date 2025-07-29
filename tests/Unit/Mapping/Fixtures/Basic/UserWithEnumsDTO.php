<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\Enums\Priority;
use Tests\Fixtures\Enums\UserStatus;

final readonly class UserWithEnumsDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?UserStatus $status = null,
        public ?Priority $priority = null,
    ) {}
}

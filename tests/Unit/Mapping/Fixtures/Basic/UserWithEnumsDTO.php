<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Fixtures\Enums\Priority;

final readonly class UserWithEnumsDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?UserStatus $status = null,
        public ?Priority $priority = null
    ) {}
}

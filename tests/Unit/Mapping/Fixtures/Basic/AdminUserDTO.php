<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

final readonly class AdminUserDTO extends BaseUserDTO
{
    public function __construct(
        int $id,
        string $name,
        string $email,
        public array $permissions = [],
    ) {
        parent::__construct($id, $name, $email);
    }
}

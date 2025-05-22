<?php

// tests/Fixtures/Enums/UserStatus.php

declare(strict_types=1);

namespace Tests\Fixtures\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case SUSPENDED = 'suspended';
}
<?php

// tests/Fixtures/Enums/Priority.php

declare(strict_types=1);

namespace Tests\Fixtures\Enums;

enum Priority: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;
    case URGENT = 4;
}

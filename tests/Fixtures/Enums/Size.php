<?php

// tests/Fixtures/Enums/Size.php

declare(strict_types=1);

namespace Tests\Fixtures\Enums;

enum Size: string
{
    case SMALL = 'S';
    case MEDIUM = 'M';
    case LARGE = 'L';
    case EXTRA_LARGE = 'XL';
}
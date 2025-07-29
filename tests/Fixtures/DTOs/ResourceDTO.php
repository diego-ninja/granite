<?php

// tests/Fixtures/DTOs/TestResourceDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;

final readonly class ResourceDTO extends GraniteDTO
{
    public mixed $resource;

    public function __construct()
    {
        $this->resource = fopen('php://memory', 'r');
    }
}

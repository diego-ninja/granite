<?php

// tests/Fixtures/DTOs/TestUninitializedDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;

final readonly class UninitializedDTO extends GraniteDTO
{
    public string $uninitializedProperty;

    public function __construct(public string $name, public ?string $description = null)
    {
        // name is required, description has default, uninitializedProperty is intentionally not set
    }
}

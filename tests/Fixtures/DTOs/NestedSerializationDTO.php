<?php

// ABOUTME: Fixture containing heterogeneous nested values for serialization tests.
// ABOUTME: Keeps arrays opaque during hydration so serialization must normalize them recursively.

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;

final readonly class NestedSerializationDTO extends GraniteDTO
{
    public function __construct(public array $items) {}
}

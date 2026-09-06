<?php

// ABOUTME: Simple destination object used by collection mapping regression tests.
// ABOUTME: Mirrors CollectionSource while remaining a distinct mapping target type.

declare(strict_types=1);

namespace Tests\Fixtures\Mapping;

final class CollectionDestination
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

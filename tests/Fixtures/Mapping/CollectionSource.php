<?php

// ABOUTME: Simple source object used by collection mapping regression tests.
// ABOUTME: Contains only public scalar state so mapper failures stay focused on collection context.

declare(strict_types=1);

namespace Tests\Fixtures\Mapping;

final class CollectionSource
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

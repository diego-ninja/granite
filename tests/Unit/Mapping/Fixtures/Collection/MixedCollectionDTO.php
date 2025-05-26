<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class MixedCollectionDTO
{
    public function __construct(
        public int $id,
        public array $items = []
    ) {}
}

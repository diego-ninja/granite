<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mapping;

use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Traits\MappingStorageTrait;

class InMemoryMappingStorage implements MappingStorage
{
    use MappingStorageTrait;
}

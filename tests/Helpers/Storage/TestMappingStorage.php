<?php

namespace Tests\Helpers\Storage;

use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Traits\MappingStorageTrait;

/**
 * Una implementación simple de MappingStorage para pruebas.
 */
class TestMappingStorage implements MappingStorage
{
    use MappingStorageTrait;
}

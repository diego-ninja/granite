<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;

class KeyValueDTO
{
    public function __construct(
        public int $id,
        #[MapFrom('mappings')]
        #[MapWith([self::class, 'extractKeys'])]
        public array $keys = [],
        #[MapFrom('mappings')]
        #[MapWith([self::class, 'extractValues'])]
        public array $values = [],
        public array $mappings = [],
    ) {}

    public static function extractKeys(array $mappings): array
    {
        return array_keys($mappings ?? []);
    }

    public static function extractValues(array $mappings): array
    {
        return array_values($mappings ?? []);
    }
}

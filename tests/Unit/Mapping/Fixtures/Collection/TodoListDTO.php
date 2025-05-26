<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

use Ninja\Granite\Mapping\Attributes\MapWith;

class TodoListDTO
{
    public function __construct(
        public int $id,
        public string $name,
        #[MapWith([self::class, 'transformItems'])]
        public array $items = []
    ) {}

    public static function transformItems(array $items): array
    {
        return array_map(fn($item) => [
            'text' => $item['text'],
            'completed' => $item['done'] ?? false
        ], $items);
    }
}

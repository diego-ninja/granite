<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use InvalidArgumentException;
use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapWith;

final readonly class FailingTransformerDTO extends GraniteDTO
{
    public function __construct(
        #[MapWith([self::class, 'failingTransformer'])]
        public string $value
    ) {}

    public static function failingTransformer(string $value): string
    {
        throw new InvalidArgumentException('This transformer always fails');
    }
}

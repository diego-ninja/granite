<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapWith;

final readonly class TransformedUserDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        #[MapWith([self::class, 'uppercaseTransformer'])]
        public string $name,
        public string $email,
    ) {}

    public static function uppercaseTransformer(string $value): string
    {
        return mb_strtoupper($value);
    }
}

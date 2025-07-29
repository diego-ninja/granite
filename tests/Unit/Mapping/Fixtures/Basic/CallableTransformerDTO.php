<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;

final readonly class CallableTransformerDTO extends GraniteDTO
{
    public function __construct(
        #[MapWith([self::class, 'uppercaseTransformer'])]
        public string $name,
        #[MapFrom('age')]
        #[MapWith([self::class, 'ageTransformer'])]
        public string $displayAge,
    ) {}

    public static function uppercaseTransformer(string $value): string
    {
        return mb_strtoupper($value);
    }

    public static function ageTransformer(int $value): string
    {
        return $value . ' years old';
    }
}

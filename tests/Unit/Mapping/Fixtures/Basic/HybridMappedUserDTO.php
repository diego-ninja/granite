<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;

final readonly class HybridMappedUserDTO extends GraniteDTO
{
    public function __construct(
        public ?string $fullName = null, // From profile

        #[MapFrom('user_type')]
        #[MapWith([self::class, 'uppercaseTransformer'])]
        public ?string $type = null, // From attribute
    ) {}

    public static function uppercaseTransformer(string $value): string
    {
        return mb_strtoupper($value);
    }
}

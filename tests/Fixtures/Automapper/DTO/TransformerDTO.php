<?php

namespace Tests\Fixtures\Automapper\DTO;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapWith;

final readonly class TransformerDTO extends GraniteDTO
{
    public int $id;

    #[MapWith([self::class, 'uppercaseTransformer'])]
    public string $name;

    public string $email;

    public static function uppercaseTransformer($value): string
    {
        return mb_strtoupper($value);
    }
}

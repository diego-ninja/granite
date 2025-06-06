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
        return strtoupper($value);
    }

    public function __construct(int $id, string $name, string $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }
}
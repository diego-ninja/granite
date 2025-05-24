<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class PrimitiveTypeDTO extends GraniteDTO
{
    public function __construct(public mixed $value) {}
}

<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;

final readonly class ChainedTransformerDTO extends GraniteDTO
{
    public function __construct(public string $text) {}
}

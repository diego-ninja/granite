<?php

namespace Tests\Fixtures\Preloader\ScanTarget\SubNamespace;

use Ninja\Granite\GraniteDTO;

readonly class OmegaDTO extends GraniteDTO
{
    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}

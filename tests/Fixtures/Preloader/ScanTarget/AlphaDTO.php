<?php

namespace Tests\Fixtures\Preloader\ScanTarget;

use Ninja\Granite\GraniteDTO; // Assuming it might check for GraniteObject instance

// To be picked up by namespace scanning for DTO/Entity pairing
readonly class AlphaDTO extends GraniteDTO
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

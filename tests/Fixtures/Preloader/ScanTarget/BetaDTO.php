<?php

namespace Tests\Fixtures\Preloader\ScanTarget;

use Ninja\Granite\GraniteDTO;

// Should be found by scan, but not paired if no BetaEntity
readonly class BetaDTO extends GraniteDTO
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}

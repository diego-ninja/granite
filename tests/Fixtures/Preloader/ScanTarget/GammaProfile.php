<?php

namespace Tests\Fixtures\Preloader\ScanTarget;

use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\TypeMapping;

// Should be found by scan, but ignored by DTO/Entity pairing logic
class GammaProfile extends MappingProfile
{
    public function configure(TypeMapping $mapping): void
    {
        // Do nothing for this test
    }
}

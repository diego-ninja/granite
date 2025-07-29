<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\MappingProfile;

class ConfigureProfile extends MappingProfile
{
    public bool $configureWasCalled = false;

    protected function configure(): void
    {
        $this->configureWasCalled = true;
    }
}

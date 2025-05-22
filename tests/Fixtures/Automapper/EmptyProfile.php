<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\MappingProfile;

class EmptyProfile extends MappingProfile
{
    protected function configure(): void
    {
        // Intentionally empty
    }
}
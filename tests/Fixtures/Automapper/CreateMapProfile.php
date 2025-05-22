<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\TypeMapping;

class CreateMapProfile extends MappingProfile
{
    public ?TypeMapping $createdMapping = null;

    protected function configure(): void
    {
        $this->createdMapping = $this->createMap('SourceClass', 'DestClass');
    }
}
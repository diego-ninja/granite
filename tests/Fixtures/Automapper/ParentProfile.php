<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\MappingProfile;

class ParentProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('Parent', 'ParentDTO')
            ->forMember('parentProp', fn($mapping) => $mapping->mapFrom('parent_field'));
    }
}

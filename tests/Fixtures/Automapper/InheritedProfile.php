<?php

namespace Tests\Fixtures\Automapper;

class InheritedProfile extends ParentProfile
{
    protected function configure(): void
    {
        parent::configure();

        $this->createMap('Child', 'ChildDTO')
            ->forMember('childProp', fn($mapping) => $mapping->mapFrom('child_field'));
    }
}

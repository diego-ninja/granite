<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\MappingProfile;

class ComplexProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('UserEntity', 'UserDTO')
            ->forMember('name', fn($mapping) => $mapping->mapFrom('full_name'))
            ->forMember('email', fn($mapping) => $mapping->mapFrom('email_address'));

        $this->createMap('OrderEntity', 'OrderDTO')
            ->forMember('total', fn($mapping) =>
            $mapping->using(fn($value) => number_format($value, 2))
            );
    }
}
<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;

class ResponseToSimpleUserProfile extends MappingProfile
{
    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        $this->createMap(UserResponseDTO::class, SimpleUserDTO::class)
            ->forMember('name', fn($mapping) => $mapping->mapFrom('displayName'));
    }
}

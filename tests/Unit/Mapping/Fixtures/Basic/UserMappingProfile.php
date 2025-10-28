<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;

class UserMappingProfile extends MappingProfile
{
    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        $this->createMap('array', ProfileMappedUserDTO::class)
            ->forMember(
                'fullName',
                fn($mapping)
                => $mapping->using(
                    fn($value, $source)
                    => ($source['firstName'] ?? '') . ' ' . ($source['lastName'] ?? ''),
                ),
            )
            ->forMember(
                'birthYear',
                fn($mapping)
                => $mapping->mapFrom('birthDate')
                    ->using(fn($value) => date('Y', strtotime($value))),
            )
            ->forMember(
                'email',
                fn($mapping)
                => $mapping->mapFrom('emailAddress'),
            );

        $this->createMap('array', HybridMappedUserDTO::class)
            ->forMember(
                'fullName',
                fn($mapping)
                => $mapping->using(
                    fn($value, $source)
                    => ($source['firstName'] ?? '') . ' ' . ($source['lastName'] ?? ''),
                ),
            );
    }
}

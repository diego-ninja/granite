<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;

class ComplexMappingProfile extends MappingProfile
{
    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        $this->createMap('array', ComplexMappedDTO::class)
            ->forMember(
                'userId',
                fn($mapping)
                => $mapping->mapFrom('user.id'),
            )
            ->forMember(
                'fullName',
                fn($mapping)
                => $mapping->using(
                    fn($value, $source)
                    => ($source['user']['firstName'] ?? '') . ' '
                    . ($source['user']['lastName'] ?? ''),
                ),
            )
            ->forMember(
                'contactInfo',
                fn($mapping)
                => $mapping->mapFrom('contact'),
            );
    }
}

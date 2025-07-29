<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\MappingProfile;
use Tests\Fixtures\Automapper\DTO\ProfileMappedDTO;

class TestMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('array', ProfileMappedDTO::class)
            ->forMember(
                'fullName',
                fn($mapping) =>
            $mapping->using(
                fn($value, $source) =>
                ($source['first_name'] ?? '') . ' ' . ($source['last_name'] ?? ''),
            ),
            )
            ->forMember(
                'birthYear',
                fn($mapping) =>
            $mapping->mapFrom('birth_date')
                ->using(fn($value) => date('Y', strtotime($value))),
            );
    }
}

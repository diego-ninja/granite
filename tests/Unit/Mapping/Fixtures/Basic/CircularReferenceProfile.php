<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\ObjectMapper;

class CircularReferenceProfile extends MappingProfile
{
    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        $this->createMap('array', CircularReferenceDTO::class)
            ->forMember(
                'parent',
                fn($mapping) =>
                $mapping->mapFrom('parent')
                    ->using(function ($value, $sourceData) {
                        if (null === $value) {
                            return null;
                        }

                        // Creamos una nueva instancia del mapper con este perfil
                        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($this));
                        return $mapper->map($value, CircularReferenceDTO::class);
                    }),
            );
    }
}

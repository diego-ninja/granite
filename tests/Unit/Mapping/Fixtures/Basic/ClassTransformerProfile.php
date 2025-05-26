<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;

class ClassTransformerProfile extends MappingProfile
{
    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        $this->createMap('array', ClassTransformerDTO::class)
            ->forMember('value', fn($mapping) => 
                $mapping->using(function($value) {
                    return strtoupper($value) . '_TRANSFORMED';
                })
            );
    }
}

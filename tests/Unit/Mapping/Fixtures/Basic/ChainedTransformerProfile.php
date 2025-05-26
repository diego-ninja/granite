<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;

class ChainedTransformerProfile extends MappingProfile
{
    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        $this->createMap('array', ChainedTransformerDTO::class)
            ->forMember('text', fn($mapping) =>
                $mapping->using(fn($value) => strtoupper($value) . '!')
            );
    }
}

<?php

namespace Ninja\Granite\Mapping;

final readonly class TypeMapping
{
    public function __construct(
        private MappingProfile $profile,
        private string         $sourceType,
        private string         $destinationType
    ) {}

    /**
     * Map property from source to destination.
     */
    public function forMember(string $destinationProperty, callable $configuration): self
    {
        $mapping = new PropertyMapping();
        $configuration($mapping);

        $this->profile->addPropertyMapping(
            $this->sourceType,
            $this->destinationType,
            $destinationProperty,
            $mapping
        );

        return $this;
    }
}
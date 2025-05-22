<?php

namespace Ninja\Granite\Mapping;

abstract class MappingProfile
{
    /**
     * Property mappings by source and destination types.
     *
     * @var array<string, array<string, PropertyMapping>>
     */
    protected array $mappings = [];

    /**
     * Configure mappings in this method.
     */
    abstract protected function configure(): void;

    public function __construct()
    {
        $this->configure();
    }

    /**
     * Create a mapping from source type to destination type.
     */
    protected function createMap(string $sourceType, string $destinationType): TypeMapping
    {
        return new TypeMapping($this, $sourceType, $destinationType);
    }

    /**
     * Add property mapping.
     */
    public function addPropertyMapping(string $sourceType, string $destinationType, string $property, PropertyMapping $mapping): void
    {
        $key = $sourceType . '->' . $destinationType;
        $this->mappings[$key][$property] = $mapping;
    }

    /**
     * Get property mapping.
     */
    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        $key = $sourceType . '->' . $destinationType;
        return $this->mappings[$key][$property] ?? null;
    }
}

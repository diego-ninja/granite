<?php

namespace Ninja\Granite\Mapping\Traits;

use Ninja\Granite\Mapping\PropertyMapping;

/**
 * Provides common implementation for mapping storage.
 */
trait MappingStorageTrait
{
    /**
     * Property mappings by source and destination types.
     *
     * @var array<string, array<string, PropertyMapping>>
     */
    protected array $mappings = [];

    /**
     * Add property mapping.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param string $property Property name
     * @param PropertyMapping $mapping Property mapping
     * @return void
     */
    public function addPropertyMapping(string $sourceType, string $destinationType, string $property, PropertyMapping $mapping): void
    {
        $key = $sourceType . '->' . $destinationType;
        $this->mappings[$key][$property] = $mapping;
    }

    /**
     * Get property mapping.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param string $property Property name
     * @return PropertyMapping|null Property mapping or null if not found
     */
    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        $key = $sourceType . '->' . $destinationType;
        return $this->mappings[$key][$property] ?? null;
    }

    /**
     * Get all mappings for a type pair.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array<string, PropertyMapping> Property mappings indexed by property name
     */
    public function getMappingsForTypes(string $sourceType, string $destinationType): array
    {
        $key = $sourceType . '->' . $destinationType;
        return $this->mappings[$key] ?? [];
    }
}
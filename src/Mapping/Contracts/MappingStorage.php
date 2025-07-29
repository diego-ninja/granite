<?php

namespace Ninja\Granite\Mapping\Contracts;

use Ninja\Granite\Mapping\PropertyMapping;

/**
 * Interface for storing and retrieving property mappings.
 */
interface MappingStorage
{
    /**
     * Add property mapping.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param string $property Property name
     * @param PropertyMapping $mapping Property mapping
     * @return void
     */
    public function addPropertyMapping(string $sourceType, string $destinationType, string $property, PropertyMapping $mapping): void;

    /**
     * Get property mapping.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @param string $property Property name
     * @return PropertyMapping|null Property mapping or null if not found
     */
    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping;

    /**
     * Get all mappings for a type pair.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return array<string, PropertyMapping> Property mappings indexed by property name
     */
    public function getMappingsForTypes(string $sourceType, string $destinationType): array;
}

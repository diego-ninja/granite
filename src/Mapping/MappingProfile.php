<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Traits\MappingStorageTrait;

/**
 * Base class for mapping profile configurations.
 */
abstract class MappingProfile implements MappingStorage
{
    use MappingStorageTrait;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->configure();
    }

    /**
     * Configure mappings in this method.
     */
    abstract protected function configure(): void;

    /**
     * Create a mapping from source type to a destination type.
     *
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     * @return TypeMapping Type mapping configuration
     */
    protected function createMap(string $sourceType, string $destinationType): TypeMapping
    {
        return new TypeMapping($this, $sourceType, $destinationType);
    }

    /**
     * Create a bidirectional mapping between two types.
     *
     * @param string $typeA First type name
     * @param string $typeB Second type name
     * @return BidirectionalTypeMapping Bidirectional mapping configuration
     */
    protected function createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping
    {
        return new BidirectionalTypeMapping($this, $typeA, $typeB);
    }
}
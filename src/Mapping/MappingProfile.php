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
     * @return list<array{0: string, 1: string}>
     */
    public function configuredTypePairs(): array
    {
        $pairs = [];

        foreach (array_keys($this->mappings) as $key) {
            $pair = explode('->', $key, 2);
            if (2 !== count($pair) || '' === $pair[0] || '' === $pair[1]) {
                continue;
            }

            $pairs[] = [$pair[0], $pair[1]];
        }

        return $pairs;
    }

    /**
     * Create a mapping from source type to a destination type.
     *
     * @param class-string $sourceType Source type name
     * @param class-string $destinationType Destination type name
     * @return TypeMapping Type mapping configuration
     */
    protected function createMap(string $sourceType, string $destinationType): TypeMapping
    {
        return new TypeMapping($this, $sourceType, $destinationType);
    }

    /**
     * Create a bidirectional mapping between two types.
     *
     * @param class-string $typeA First type name
     * @param class-string $typeB Second type name
     * @return BidirectionalTypeMapping Bidirectional mapping configuration
     */
    protected function createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping
    {
        return new BidirectionalTypeMapping($this, $typeA, $typeB);
    }
}

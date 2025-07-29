<?php

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Mapping\Transformers\CollectionTransformer;

/**
 * Attribute to configure collection mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapCollection
{
    /**
     * @param class-string $destinationType The class name for collection items
     * @param bool $preserveKeys Whether to preserve array keys
     * @param bool $recursive Whether to handle nested collections recursively
     * @param mixed $itemTransformer Optional transformer for collection items
     */
    public function __construct(
        public string $destinationType,
        public bool $preserveKeys = false,
        public bool $recursive = false,
        public mixed $itemTransformer = null,
    ) {}

    /**
     * Create a collection transformer from this attribute.
     */
    public function createTransformer(mixed $mapper): Transformer
    {
        return new CollectionTransformer(
            $this->destinationType,
            is_object($mapper) && $mapper instanceof \Ninja\Granite\Mapping\Contracts\Mapper ? $mapper : null,
            $this->preserveKeys,
            $this->recursive,
            $this->itemTransformer,
        );
    }
}

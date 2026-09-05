<?php
// ABOUTME: Defines MapFrom as part of the object mapping pipeline.
// ABOUTME: Owns the MapFrom boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to specify source property for mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapFrom
{
    public function __construct(
        public string $source,
    ) {}
}

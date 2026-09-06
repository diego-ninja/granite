<?php
// ABOUTME: Defines MapWith as part of the object mapping pipeline.
// ABOUTME: Owns the MapWith boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to specify transformer for property mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapWith
{
    public function __construct(
        public mixed $transformer,
    ) {}
}

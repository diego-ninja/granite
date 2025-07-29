<?php

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to specify that a property should be mapped bidirectionally.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapBidirectional
{
    /**
     * Constructor.
     *
     * @param string $otherProperty Name of the corresponding property in the other class
     */
    public function __construct(
        public string $otherProperty,
    ) {}
}

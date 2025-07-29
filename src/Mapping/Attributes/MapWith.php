<?php

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

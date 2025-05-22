<?php

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to specify source property for mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapFrom
{
    public function __construct(
        public string $source
    ) {}
}
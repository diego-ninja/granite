<?php

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to specify a default value for a property when mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapDefault
{
    /**
     * Constructor.
     *
     * @param mixed $value Default value to use when the property is null or condition fails
     */
    public function __construct(
        public mixed $value
    ) {}
}
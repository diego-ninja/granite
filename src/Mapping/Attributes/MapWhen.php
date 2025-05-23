<?php

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to apply a condition to a property mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MapWhen
{
    /**
     * Constructor.
     *
     * @param mixed $condition A callable or the name of a method in the source class
     */
    public function __construct(
        public mixed $condition
    ) {}
}
<?php

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;

/**
 * Attribute to hide a property during serialization.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Hidden
{
    /**
     * Constructor.
     */
    public function __construct() {}
}

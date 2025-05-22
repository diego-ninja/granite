<?php

namespace Ninja\Granite\Mapping\Attributes;

use Attribute;

/**
 * Attribute to ignore property during mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Ignore
{
    public function __construct() {}
}
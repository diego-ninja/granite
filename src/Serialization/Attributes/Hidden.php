<?php
// ABOUTME: Defines Hidden as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the Hidden boundary between metadata and serialized values.

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

<?php
// ABOUTME: Defines Ignore as part of the object mapping pipeline.
// ABOUTME: Owns the Ignore boundary between mapping configuration and execution.

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

<?php
// ABOUTME: Defines SerializedName as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the SerializedName boundary between metadata and serialized values.

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;

/**
 * Attribute to specify a custom name for a property when serialized.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class SerializedName
{
    /**
     * Constructor.
     *
     * @param string $name The name to use when serializing
     */
    public function __construct(
        public string $name,
    ) {}
}

<?php

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;

/**
 * Attribute to configure Carbon for relative date parsing.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CarbonRelative
{
    /**
     * Constructor.
     *
     * @param bool $enabled Whether to enable relative parsing
     * @param string|null $baseDate Base date for relative calculations
     */
    public function __construct(
        public bool $enabled = true,
        public ?string $baseDate = null,
    ) {}
}

<?php

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;
use DateTimeInterface;

/**
 * Attribute to specify date range validation for Carbon instances.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CarbonRange
{
    /**
     * Constructor.
     *
     * @param DateTimeInterface|string|null $min Minimum allowed date
     * @param DateTimeInterface|string|null $max Maximum allowed date
     * @param string|null $message Custom validation error message
     */
    public function __construct(
        public DateTimeInterface|string|null $min = null,
        public DateTimeInterface|string|null $max = null,
        public ?string $message = null,
    ) {}
}

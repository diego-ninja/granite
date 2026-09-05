<?php
// ABOUTME: Defines CarbonRange as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the CarbonRange boundary between metadata and serialized values.

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;
use DateTimeInterface;
use Ninja\Granite\Validation\Rules\Carbon\Range as RangeRule;

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

    public function asRule(): RangeRule
    {
        $rule = new RangeRule(min: $this->min, max: $this->max);
        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

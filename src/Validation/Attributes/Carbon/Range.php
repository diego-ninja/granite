<?php

namespace Ninja\Granite\Validation\Attributes\Carbon;

use Attribute;
use DateTimeInterface;
use Ninja\Granite\Validation\Rules\Carbon\Range as RangeRule;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Range
{
    /**
     * Constructor.
     *
     * @param DateTimeInterface|string|null $min Minimum allowed date
     * @param DateTimeInterface|string|null $max Maximum allowed date
     * @param string|null $timezone Timezone for date comparisons
     * @param string|null $message Custom error message
     * @see RangeRule
     */
    public function __construct(
        private DateTimeInterface|string|null $min = null,
        private DateTimeInterface|string|null $max = null,
        private ?string $timezone = null,
        private ?string $message = null,
    ) {}

    /**
     * Create a validation rule from this attribute.
     *
     * @return RangeRule Validation rule
     */
    public function asRule(): RangeRule
    {
        $rule = new RangeRule(
            min: $this->min,
            max: $this->max,
            timezone: $this->timezone,
        );

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

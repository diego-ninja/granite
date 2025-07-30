<?php

namespace Ninja\Granite\Validation\Attributes\Carbon;

use Attribute;
use Ninja\Granite\Validation\Rules\Carbon\Weekend as WeekendRule;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Weekend
{
    /**
     * Constructor.
     *
     * @param string|null $timezone Timezone for comparison
     * @param string|null $message Custom error message
     * @see WeekendRule
     */
    public function __construct(
        private ?string $timezone = null,
        private ?string $message = null,
    ) {}

    /**
     * Create a validation rule from this attribute.
     *
     * @return WeekendRule Validation rule
     */
    public function asRule(): WeekendRule
    {
        $rule = new WeekendRule(
            timezone: $this->timezone,
        );

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

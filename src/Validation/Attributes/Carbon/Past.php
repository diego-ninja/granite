<?php

namespace Ninja\Granite\Validation\Attributes\Carbon;

use Attribute;
use Ninja\Granite\Validation\Rules\Carbon\Past as PastRule;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Past
{
    /**
     * Constructor.
     *
     * @param string|null $timezone Timezone for comparison
     * @param bool $allowToday Whether to allow today's date
     * @param string|null $message Custom error message
     * @see PastRule
     */
    public function __construct(
        private ?string $timezone = null,
        private bool $allowToday = false,
        private ?string $message = null,
    ) {}

    /**
     * Create a validation rule from this attribute.
     *
     * @return PastRule Validation rule
     */
    public function asRule(): PastRule
    {
        $rule = new PastRule(
            timezone: $this->timezone,
            allowToday: $this->allowToday,
        );

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

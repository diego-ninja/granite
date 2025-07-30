<?php

namespace Ninja\Granite\Validation\Attributes\Carbon;

use Attribute;
use Ninja\Granite\Validation\Rules\Carbon\BusinessDay as BusinessDayRule;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BusinessDay
{
    /**
     * Constructor.
     *
     * @param array<int> $businessDays Array of business day numbers (1=Monday, 7=Sunday)
     * @param string|null $timezone Timezone for comparison
     */
    public function __construct(
        private array $businessDays = [1, 2, 3, 4, 5], // Mon-Fri by default
        private ?string $timezone = null,
        private ?string $message = null,
    ) {}

    /**
     * Create a validation rule from this attribute.
     *
     * @return BusinessDayRule Validation rule
     */
    public function asRule(): BusinessDayRule
    {
        $rule = new BusinessDayRule(
            businessDays: $this->businessDays,
            timezone: $this->timezone,
        );

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

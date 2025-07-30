<?php

namespace Ninja\Granite\Validation\Attributes\Carbon;

use Attribute;
use Ninja\Granite\Validation\Rules\Carbon\Age as AgeRule;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Age
{
    /**
     * Constructor.
     *
     * @param int|null $minAge Minimum age in years
     * @param int|null $maxAge Maximum age in years
     * @param string|null $timezone Timezone for age calculations
     */
    public function __construct(
        private ?int    $minAge = null,
        private ?int    $maxAge = null,
        private ?string $timezone = null,
        private ?string $message = null,
    ) {}

    /**
     * Create a validation rule from this attribute.
     *
     * @return AgeRule Validation rule
     */
    public function asRule(): AgeRule
    {
        $rule = new AgeRule(
            minAge: $this->minAge,
            maxAge: $this->maxAge,
            timezone: $this->timezone,
        );

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

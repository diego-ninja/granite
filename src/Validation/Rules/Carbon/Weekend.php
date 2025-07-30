<?php

namespace Ninja\Granite\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Validation\Rules\AbstractRule;

final class Weekend extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param array<int> $weekendDays Array of weekend day numbers (0=Sunday, 6=Saturday)
     * @param string|null $timezone Timezone for comparison
     */
    public function __construct(
        private readonly array $weekendDays = [0, 6], // Sat-Sun by default
        private readonly ?string $timezone = null,
    ) {}

    /**
     * Validate that the date is a weekend day.
     *
     * @param mixed $value The value to validate
     * @param array|null $allData All data being validated (optional)
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        if (null === $value) {
            return true;
        }

        // Convert to Carbon if not already
        if ( ! CarbonSupport::isCarbonInstance($value)) {
            $value = CarbonSupport::create($value, null, $this->timezone);
            if (null === $value) {
                return false;
            }
        }

        /** @var Carbon|CarbonImmutable $value */
        // Carbon dayOfWeek: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
        // We want to check if it's a weekend (Saturday-Sunday = 6,0)
        return in_array($value->dayOfWeek, $this->weekendDays, true);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return sprintf("%s must be a weekend day (Saturday or Sunday)", $property);
    }
}

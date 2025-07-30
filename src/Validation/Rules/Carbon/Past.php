<?php

namespace Ninja\Granite\Validation\Rules\Carbon;

use Carbon\CarbonInterface;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Validation\Rules\AbstractRule;

/**
 * Validation rule for Carbon past dates.
 * Validates that a date is in the past.
 */
final class Past extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param string|null $timezone Timezone for comparison
     * @param bool $allowToday Whether to allow today's date
     */
    public function __construct(
        private readonly ?string $timezone = null,
        private readonly bool $allowToday = false,
    ) {}

    /**
     * Validate that the date is in the past.
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

        /** @var CarbonInterface $value */
        $now = CarbonSupport::create('now', null, $this->timezone);
        if (null === $now) {
            return false;
        }

        if ($this->allowToday) {
            if ($now instanceof CarbonInterface) {
                return $value->endOfDay()->lessThanOrEqualTo($now->endOfDay());
            }
        }

        return $value->lessThan($now);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        if ($this->allowToday) {
            return sprintf("%s must be today or in the past", $property);
        }

        return sprintf("%s must be in the past", $property);
    }
}

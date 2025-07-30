<?php

namespace Ninja\Granite\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Validation\Rules\AbstractRule;

/**
 * Validation rule for Carbon age validation.
 * Validates that a date represents a valid age within specified bounds.
 */
final class Age extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param int|null $minAge Minimum age in years
     * @param int|null $maxAge Maximum age in years
     * @param string|null $timezone Timezone for age calculations
     */
    public function __construct(
        private readonly ?int $minAge = null,
        private readonly ?int $maxAge = null,
        private readonly ?string $timezone = null,
    ) {}

    /**
     * Validate that the date represents a valid age.
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
        $now = CarbonSupport::create('now', null, $this->timezone);
        if (null === $now) {
            return false;
        }

        $age = round($value->diffInYears($now));

        // Check minimum age
        if (null !== $this->minAge && $age < $this->minAge) {
            return false;
        }

        // Check maximum age
        return ! (null !== $this->maxAge && $age > $this->maxAge);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        if (null !== $this->minAge && null !== $this->maxAge) {
            return sprintf(
                "%s must represent an age between %d and %d years",
                $property,
                $this->minAge,
                $this->maxAge,
            );
        }

        if (null !== $this->minAge) {
            return sprintf(
                "%s must represent an age of at least %d years",
                $property,
                $this->minAge,
            );
        }

        if (null !== $this->maxAge) {
            return sprintf(
                "%s must represent an age of at most %d years",
                $property,
                $this->maxAge,
            );
        }

        return sprintf("%s must be a valid birth date", $property);
    }
}

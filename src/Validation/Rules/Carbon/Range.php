<?php

namespace Ninja\Granite\Validation\Rules\Carbon;

use DateTimeInterface;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Validation\Rules\AbstractRule;

/**
 * Validation rule for Carbon date ranges.
 * Validates that a Carbon date is within specified minimum and maximum bounds.
 */
final class Range extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param DateTimeInterface|string|null $min Minimum allowed date
     * @param DateTimeInterface|string|null $max Maximum allowed date
     * @param string|null $timezone Timezone for date comparisons
     */
    public function __construct(
        private readonly DateTimeInterface|string|null $min = null,
        private readonly DateTimeInterface|string|null $max = null,
        private readonly ?string $timezone = null,
    ) {}

    /**
     * Validate that the Carbon date is within the specified range.
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

        // Convert value to Carbon if it isn't already
        if ( ! CarbonSupport::isCarbonInstance($value)) {
            $value = CarbonSupport::create($value, null, $this->timezone);
            if (null === $value) {
                return false;
            }
        }

        // Check minimum bound
        if (null !== $this->min) {
            $minDate = $this->parseRangeDate($this->min);
            if (null !== $minDate && $value < $minDate) {
                return false;
            }
        }

        // Check maximum bound
        if (null !== $this->max) {
            $maxDate = $this->parseRangeDate($this->max);
            if (null !== $maxDate && $value > $maxDate) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        if (null !== $this->min && null !== $this->max) {
            return sprintf(
                "%s must be between %s and %s",
                $property,
                $this->formatDate($this->min),
                $this->formatDate($this->max),
            );
        }

        if (null !== $this->min) {
            return sprintf(
                "%s must be after %s",
                $property,
                $this->formatDate($this->min),
            );
        }

        if (null !== $this->max) {
            return sprintf(
                "%s must be before %s",
                $property,
                $this->formatDate($this->max),
            );
        }

        return sprintf("%s must be a valid date", $property);
    }

    /**
     * Parse range date to DateTimeInterface.
     *
     * @param DateTimeInterface|string $date Date to parse
     * @return DateTimeInterface|null Parsed date
     */
    private function parseRangeDate(DateTimeInterface|string $date): ?DateTimeInterface
    {
        if ($date instanceof DateTimeInterface) {
            return $date;
        }

        return CarbonSupport::create($date, null, $this->timezone);
    }

    /**
     * Format date for error message display.
     *
     * @param DateTimeInterface|string $date Date to format
     * @return string Formatted date string
     */
    private function formatDate(DateTimeInterface|string $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        return (string) $date;
    }
}

<?php

namespace Ninja\Granite\Validation\Rules;

class Max extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param int|float $max Maximum value
     */
    public function __construct(
        private readonly int|float $max
    ) {}

    /**
     * Check if the value meets the maximum requirement.
     *
     * @param mixed $value
     * @param array|null $allData
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return strlen($value) <= $this->max;
        } elseif (is_int($value) || is_float($value)) {
            return $value <= $this->max;
        } elseif (is_array($value)) {
            return count($value) <= $this->max;
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
        return sprintf("%s must be at most %s", $property, $this->max);
    }
}
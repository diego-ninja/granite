<?php

namespace Ninja\Granite\Validation\Rules;

class Min extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param int|float $min Minimum value
     */
    public function __construct(
        private readonly int|float $min
    )
    {
    }

    /**
     * Check if the value meets the minimum requirement.
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
            return mb_strlen($value) >= $this->min;
        } elseif (is_int($value) || is_float($value)) {
            return $value >= $this->min;
        } elseif (is_array($value)) {
            return count($value) >= $this->min;
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
        return sprintf("%s must be at least %s", $property, $this->min);
    }
}
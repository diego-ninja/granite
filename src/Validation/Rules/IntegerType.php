<?php

namespace Ninja\Granite\Validation\Rules;

class IntegerType extends AbstractRule
{
    /**
     * Check if the value is an integer.
     *
     * @param mixed $value
     * @param array|null $allData
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        return null === $value || is_int($value);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return sprintf("%s must be an integer", $property);
    }
}

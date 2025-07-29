<?php

namespace Ninja\Granite\Validation\Rules;

class ArrayType extends AbstractRule
{
    /**
     * Check if the value is an array.
     *
     * @param mixed $value
     * @param array|null $allData
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        return null === $value || is_array($value);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return sprintf("%s must be an array", $property);
    }
}

<?php

namespace Ninja\Granite\Validation\Rules;

class IpAddress extends AbstractRule
{
    /**
     * Check if the value is a valid IP address.
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

        if (!is_string($value)) {
            return false;
        }

        return (bool)filter_var($value, FILTER_VALIDATE_IP);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return sprintf("%s must be a valid IP address", $property);
    }
}
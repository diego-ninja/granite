<?php

namespace Ninja\Granite\Validation\Rules;

class Regex extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param string $pattern Regular expression pattern
     */
    public function __construct(
        private readonly string $pattern
    ) {}

    /**
     * Check if the value matches the pattern.
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

        return (bool)preg_match($this->pattern, $value);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return sprintf("%s must match the pattern %s", $property, $this->pattern);
    }
}
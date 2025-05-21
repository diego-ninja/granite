<?php

namespace Ninja\Granite\Validation;

interface ValidationRule
{
    /**
     * Check if the value passes this validation rule.
     *
     * @param mixed $value The value to validate
     * @param array|null $allData All data being validated (optional)
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool;

    /**
     * Get the error message for this rule.
     *
     * @param string $property Property name being validated
     * @return string Error message
     */
    public function message(string $property): string;
}
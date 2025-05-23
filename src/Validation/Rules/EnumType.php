<?php

namespace Ninja\Granite\Validation\Rules;

use UnitEnum;

class EnumType extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param string|null $enumClass Optional specific enum class
     */
    public function __construct(private readonly ?string $enumClass = null)
    {
    }

    /**
     * Check if the value is a valid enum or enum case.
     *
     * @param mixed $value The value to validate
     * @param array|null $allData All data being validated (optional)
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        if ($value === null) {
            return true;
        }

        // If the value is already an enum
        if ($value instanceof UnitEnum) {
            return $this->enumClass === null || $value instanceof $this->enumClass;
        }

        // If the value is a string or integer (potential enum case)
        if (is_string($value) || is_int($value)) {
            if ($this->enumClass === null) {
                return false; // Need specific enum class to validate string/int values
            }

            // Check if the value matches any case in the enum
            return in_array($value, array_map(
                fn($case) => $case->value ?? $case->name,
                $this->enumClass::cases()
            ), true);
        }

        return false;
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        if ($this->enumClass) {
            return sprintf("%s must be a valid case of %s", $property, $this->enumClass);
        }

        return sprintf("%s must be a valid enum", $property);
    }
}
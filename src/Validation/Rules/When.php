<?php

namespace Ninja\Granite\Validation\Rules;

use Ninja\Granite\Validation\ValidationRule;

class When extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param callable $condition Condition callback that returns a boolean
     * @param ValidationRule $rule Rule to apply if condition is true
     */
    public function __construct(
        private readonly mixed $condition,
        private readonly ValidationRule $rule
    ) {
    }

    /**
     * Check if the value passes the validation rule.
     *
     * @param mixed $value The value to validate
     * @param array|null $allData All data being validated (optional)
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        // If the condition is false, validation passes automatically
        if (!call_user_func($this->condition, $value, $allData)) {
            return true;
        }

        // Check the underlying rule
        return $this->rule->validate($value, $allData);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return $this->rule->message($property);
    }
}
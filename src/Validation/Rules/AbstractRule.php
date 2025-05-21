<?php

namespace Ninja\Granite\Validation\Rules;

use Ninja\Granite\Validation\ValidationRule;

abstract class AbstractRule implements ValidationRule
{
    /**
     * Custom error message.
     */
    protected ?string $customMessage = null;

    /**
     * Set a custom error message for this rule.
     *
     * @param string $message Custom error message
     * @return $this For method chaining
     */
    public function withMessage(string $message): static
    {
        $this->customMessage = $message;
        return $this;
    }

    /**
     * Get the default error message for this rule.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    abstract protected function defaultMessage(string $property): string;

    /**
     * Get the error message for this rule.
     *
     * @param string $property Property name being validated
     * @return string Error message
     */
    public function message(string $property): string
    {
        return $this->customMessage ?? $this->defaultMessage($property);
    }
}
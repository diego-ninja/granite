<?php
// ABOUTME: Defines Regex as part of validation rule definition and execution.
// ABOUTME: Owns the Regex boundary between rule definitions and validation results.

namespace Ninja\Granite\Validation\Rules;

use InvalidArgumentException;

class Regex extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param string $pattern Regular expression pattern
     */
    public function __construct(
        private readonly string $pattern,
    ) {
        if ('' === $this->pattern || ! $this->isValidPattern()) {
            throw new InvalidArgumentException(sprintf('Invalid regular expression pattern: %s', $this->pattern));
        }
    }

    /**
     * Check if the value matches the pattern.
     *
     * @param mixed $value
     * @param array<array-key, mixed>|null $allData
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        if (null === $value) {
            return true;
        }

        if ( ! is_string($value)) {
            return false;
        }

        return (bool) preg_match($this->pattern, $value);
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

    private function isValidPattern(): bool
    {
        set_error_handler(static fn(): bool => true);
        try {
            return false !== preg_match($this->pattern, '');
        } finally {
            restore_error_handler();
        }
    }
}

<?php

namespace Ninja\Granite\Validation\Rules;

class Callback extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param callable $callback Validation callback that returns a boolean
     */
    public function __construct(
        private readonly mixed $callback,
    ) {}

    /**
     * Check if the value passes the callback validation.
     *
     * @param mixed $value
     * @param array|null $allData
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        return (null === $value) || call_user_func($this->callback, $value);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        return sprintf("%s is invalid", $property);
    }
}

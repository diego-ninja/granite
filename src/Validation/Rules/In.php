<?php

namespace Ninja\Granite\Validation\Rules;

class In extends AbstractRule
{
    /**
     * Constructor.
     *
     * @param array $values Allowed values
     */
    public function __construct(
        private readonly array $values,
    ) {}

    /**
     * Check if the value is one of the allowed values.
     *
     * @param mixed $value
     * @param array|null $allData
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        return null === $value || in_array($value, $this->values, true);
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        $valuesList = implode(', ', array_map(function ($val) {
            if (is_string($val)) {
                return "'{$val}'";
            }
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                return (string) $val;
            }
            return 'non-displayable';
        }, $this->values));

        return sprintf("%s must be one of: %s", $property, $valuesList);
    }
}

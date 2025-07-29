<?php

namespace Ninja\Granite\Validation\Rules;

use Ninja\Granite\Validation\ValidationRule;

class Each extends AbstractRule
{
    /**
     * The index of the item that failed validation.
     */
    private string|int|null $currentIndex = null;

    /**
     * The rule that failed for the current item.
     */
    private ?ValidationRule $failedRule = null;

    /**
     * Constructor.
     *
     * @param ValidationRule|array<ValidationRule> $rules Rules to apply to each item
     */
    public function __construct(
        private readonly ValidationRule|array $rules,
    ) {}

    /**
     * Check if each element in the array passes the validation rules.
     *
     * @param mixed $value The value to validate
     * @param array|null $allData All data being validated (optional)
     * @return bool Whether the value is valid
     */
    public function validate(mixed $value, ?array $allData = null): bool
    {
        $this->currentIndex = null;
        $this->failedRule = null;

        // If not an array, or empty, there's nothing to validate
        if ( ! is_array($value) || empty($value)) {
            return true;
        }

        // Apply rules to each item
        foreach ($value as $index => $item) {
            // For a single rule
            if ($this->rules instanceof ValidationRule) {
                if ( ! $this->rules->validate($item, $allData)) {
                    $this->currentIndex = $index;
                    return false;
                }
            }
            // For multiple rules
            else {
                foreach ($this->rules as $rule) {
                    if ( ! $rule->validate($item, $allData)) {
                        $this->currentIndex = $index;
                        $this->failedRule = $rule;
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Get the default error message.
     *
     * @param string $property Property name being validated
     * @return string Default error message
     */
    protected function defaultMessage(string $property): string
    {
        if (null !== $this->currentIndex) {
            $itemProperty = sprintf("%s[%s]", $property, $this->currentIndex);

            if (null !== $this->failedRule) {
                return $this->failedRule->message($itemProperty);
            }

            return sprintf("Item at index %s in %s is invalid", $this->currentIndex, $property);
        }

        return sprintf("All items in %s must be valid", $property);
    }
}

<?php

namespace Ninja\Granite\Validation;

class RuleCollection
{
    /**
     * The property name.
     */
    private string $property;

    /**
     * The validation rules.
     *
     * @var ValidationRule[]
     */
    private array $rules = [];

    /**
     * Constructor.
     *
     * @param string $property The property name
     * @param ValidationRule|array<mixed> $rules Initial rules (optional) - array may contain mixed elements that will be filtered
     */
    public function __construct(string $property, ValidationRule|array $rules = [])
    {
        $this->property = $property;

        if ($rules instanceof ValidationRule) {
            $this->rules[] = $rules;
        } elseif (is_array($rules)) {
            foreach ($rules as $rule) {
                if ($rule instanceof ValidationRule) {
                    $this->rules[] = $rule;
                }
            }
        }
    }

    /**
     * Add a rule to the collection.
     *
     * @param ValidationRule $rule The rule to add
     * @return $this For method chaining
     */
    public function add(ValidationRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * Validate a value against all rules in the collection.
     *
     * @param mixed $value The value to validate
     * @param array|null $allData All data being validated (optional)
     * @return string[] Array of error messages, empty if valid
     */
    public function validate(mixed $value, ?array $allData = null): array
    {
        $errors = [];

        foreach ($this->rules as $rule) {
            if ( ! $rule->validate($value, $allData)) {
                $errors[] = $rule->message($this->property);
            }
        }

        return $errors;
    }

    /**
     * Get the property name.
     *
     * @return string Property name
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Get all rules in the collection.
     *
     * @return ValidationRule[] Array of rules
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}

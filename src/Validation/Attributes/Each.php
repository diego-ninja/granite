<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;
use Ninja\Granite\Validation\ValidationRule;

/**
 * Attribute for validating each item in an array.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Each
{
    /**
     * @var ValidationRule|array<ValidationRule> Rules to apply to each item
     */
    private ValidationRule|array $rules;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    /**
     * @param ValidationRule|array<ValidationRule> $rules
     * @param string|null $message
     */
    public function __construct(ValidationRule|array $rules, ?string $message = null)
    {
        $this->rules = $rules;
        $this->message = $message;
    }

    public function asRule(): Rules\Each
    {
        $rule = new Rules\Each($this->rules);

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

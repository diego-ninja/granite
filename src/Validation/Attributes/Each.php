<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\ValidationRule;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for validating each item in an array.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Each
{
    /**
     * @var ValidationRule|ValidationRule[] Rules to apply to each item
     */
    private ValidationRule|array $rules;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(ValidationRule|array $rules, ?string $message = null)
    {
        $this->rules = $rules;
        $this->message = $message;
    }

    public function asRule(): Rules\Each
    {
        $rule = new Rules\Each($this->rules);

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}
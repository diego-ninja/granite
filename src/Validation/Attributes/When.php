<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;
use Ninja\Granite\Validation\ValidationRule;

/**
 * Attribute for conditional validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class When
{
    /**
     * @var callable Condition callback
     */
    private $condition;

    /**
     * @var ValidationRule Rule to apply if condition is true
     */
    private ValidationRule $rule;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(callable $condition, ValidationRule $rule, ?string $message = null)
    {
        $this->condition = $condition;
        $this->rule = $rule;
        $this->message = $message;
    }

    public function asRule(): Rules\When
    {
        $rule = new Rules\When($this->condition, $this->rule);

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

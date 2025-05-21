<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for array type validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ArrayType
{
    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    /**
     * Constructor.
     *
     * @param string|null $message Custom error message
     */
    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    /**
     * Get the validation rule.
     *
     * @return Rules\ArrayType
     */
    public function asRule(): Rules\ArrayType
    {
        $rule = new Rules\ArrayType();
        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

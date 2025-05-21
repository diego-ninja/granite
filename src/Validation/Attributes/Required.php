<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for required field validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Required
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
     * Create a validation rule from this attribute.
     *
     * @return Rules\Required Validation rule
     */
    public function asRule(): Rules\Required
    {
        $rule = new Rules\Required();

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}
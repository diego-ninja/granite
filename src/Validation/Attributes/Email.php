<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for email validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Email
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
     * @return Rules\Email
     */
    public function asRule(): Rules\Email
    {
        $rule = new Rules\Email();
        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

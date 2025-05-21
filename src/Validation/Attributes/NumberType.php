<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for number type validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NumberType
{
    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    public function asRule(): Rules\NumberType
    {
        $rule = new Rules\NumberType();

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

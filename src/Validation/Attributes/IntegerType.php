<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for integer type validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class IntegerType
{
    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    public function asRule(): Rules\IntegerType
    {
        $rule = new Rules\IntegerType();

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

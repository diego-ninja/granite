<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for enum validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class EnumType
{
    /**
     * @var string|null Specific enum class
     */
    private ?string $enumClass;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    /**
     * Constructor.
     *
     * @param string|null $enumClass Optional specific enum class
     * @param string|null $message Custom error message
     */
    public function __construct(?string $enumClass = null, ?string $message = null)
    {
        $this->enumClass = $enumClass;
        $this->message = $message;
    }

    /**
     * Create a validation rule from this attribute.
     *
     * @return Rules\EnumType Validation rule
     */
    public function asRule(): Rules\EnumType
    {
        $rule = new Rules\EnumType($this->enumClass);

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}
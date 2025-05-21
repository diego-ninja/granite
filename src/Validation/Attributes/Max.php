<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for minimum value/length validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Max
{
    /**
     * Constructor.
     *
     * @param int|float $max Maximum value
     * @param string|null $message Custom error message
     */
    public function __construct(private int|float $max, private ?string $message = null)
    {
    }

    /**
     * Create a validation rule from this attribute.
     *
     * @return Rules\Max Validation rule
     */
    public function asRule(): Rules\Max
    {
        $rule = new Rules\Max($this->max);

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}


<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for minimum value/length validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Min
{
    /**
     * Constructor.
     *
     * @param int|float $min Minimum value
     * @param string|null $message Custom error message
     */
    public function __construct(private int|float $min, private ?string $message = null) {}

    /**
     * Create a validation rule from this attribute.
     *
     * @return Rules\Min Validation rule
     */
    public function asRule(): Rules\Min
    {
        $rule = new Rules\Min($this->min);

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

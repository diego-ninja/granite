<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for validating value is in a set of allowed values.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class In
{
    /**
     * @var array Allowed values
     */
    private array $values;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(array $values, ?string $message = null)
    {
        $this->values = $values;
        $this->message = $message;
    }

    public function asRule(): Rules\In
    {
        $rule = new Rules\In($this->values);

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

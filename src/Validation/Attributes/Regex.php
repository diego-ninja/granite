<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for regex pattern validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex
{
    /**
     * @var string Regular expression pattern
     */
    private string $pattern;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(string $pattern, ?string $message = null)
    {
        $this->pattern = $pattern;
        $this->message = $message;
    }

    public function asRule(): Rules\Regex
    {
        $rule = new Rules\Regex($this->pattern);

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

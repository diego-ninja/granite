<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for URL validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Url
{
    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    public function asRule(): Rules\Url
    {
        $rule = new Rules\Url();

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

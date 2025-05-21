<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for IP address validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class IpAddress
{
    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    public function asRule(): Rules\IpAddress
    {
        $rule = new Rules\IpAddress();

        if ($this->message !== null) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

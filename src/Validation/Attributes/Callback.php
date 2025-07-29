<?php

namespace Ninja\Granite\Validation\Attributes;

use Attribute;
use Ninja\Granite\Validation\Rules;

/**
 * Attribute for custom callback validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Callback
{
    /**
     * @var callable Validation callback
     */
    private $callback;

    /**
     * @var string|null Custom error message
     */
    private ?string $message;

    public function __construct(callable $callback, ?string $message = null)
    {
        $this->callback = $callback;
        $this->message = $message;
    }

    public function asRule(): Rules\Callback
    {
        $rule = new Rules\Callback($this->callback);

        if (null !== $this->message) {
            $rule->withMessage($this->message);
        }

        return $rule;
    }
}

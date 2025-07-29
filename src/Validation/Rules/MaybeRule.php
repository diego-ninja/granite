<?php

namespace Ninja\Granite\Validation\Rules;

use Ninja\Granite\Monads\Contracts\Maybe;
use Ninja\Granite\Monads\Factories\Maybe;
use Ninja\Granite\Validation\ValidationResult;

abstract class MaybeRule extends AbstractRule
{
    /**
     * Validate and return Maybe<ValidationResult>
     */
    public function validateMaybe(mixed $value, ?array $allData = null): Maybe
    {
        if ($this->validate($value, $allData)) {
            return Maybe::some(new ValidationResult(true, null));
        }

        return Maybe::some(new ValidationResult(false, $this->message('property')));
    }
}
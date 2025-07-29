<?php

namespace Ninja\Granite\Validation;

final readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?string $errorMessage
    ) {}
}
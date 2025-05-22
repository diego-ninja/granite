<?php

namespace Tests\Fixtures\DTOs;

final readonly class ProtectedMethodsDTO
{
    public function __construct(
        public string $name,
        public string $hiddenField
    ) {}

    protected static function serializedNames(): array
    {
        return ['name' => 'custom_name'];
    }

    protected static function hiddenProperties(): array
    {
        return ['hiddenField'];
    }
}
<?php

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class MixedSerializationDTO
{
    public function __construct(
        #[SerializedName('given_name')]
        public string $firstName,

        public string $lastName,

        #[Hidden]
        public string $password,

        public string $secret
    ) {}

    protected static function serializedNames(): array
    {
        return [
            'firstName' => 'first_name', // Should be overridden by attribute
            'lastName' => 'family_name'
        ];
    }

    protected static function hiddenProperties(): array
    {
        return ['password', 'secret']; // password also has attribute
    }
}
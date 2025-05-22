<?php

namespace Tests\Fixtures\DTOs;

final readonly class MethodBasedDTO
{
    public function __construct(
        public string $email,
        public string $username,
        public string $internalId,
        public \DateTimeInterface $createdAt
    ) {}

    protected static function serializedNames(): array
    {
        return [
            'email' => 'email_address',
            'username' => 'user_name'
        ];
    }

    protected static function hiddenProperties(): array
    {
        return ['internalId', 'createdAt'];
    }
}
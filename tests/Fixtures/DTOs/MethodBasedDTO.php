<?php

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;

final readonly class MethodBasedDTO
{
    public function __construct(
        public string $email,
        public string $username,
        public string $internalId,
        public DateTimeInterface $createdAt,
    ) {}

    private static function serializedNames(): array
    {
        return [
            'email' => 'email_address',
            'username' => 'user_name',
        ];
    }

    private static function hiddenProperties(): array
    {
        return ['internalId', 'createdAt'];
    }
}

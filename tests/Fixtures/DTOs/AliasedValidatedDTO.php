<?php

// ABOUTME: Fixture combining a serialized property alias with required validation.
// ABOUTME: Exercises canonical property lookup before validation and hydration.

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Validation\Attributes\Required;

final readonly class AliasedValidatedDTO extends GraniteDTO
{
    public function __construct(
        #[Required]
        public string $displayName,
        public string $secret = 'token',
    ) {}

    protected static function serializedNames(): array
    {
        return ['displayName' => 'display_name'];
    }

    protected static function hiddenProperties(): array
    {
        return ['secret'];
    }
}

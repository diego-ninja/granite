<?php

// ABOUTME: Fixture combining a serialized property alias with required validation.
// ABOUTME: Exercises canonical property lookup before validation and hydration.

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Validation\Attributes\Required;

final readonly class AliasedValidatedDTO extends GraniteDTO
{
    public function __construct(
        #[SerializedName('display_name')]
        #[Required]
        public string $displayName,
    ) {}
}

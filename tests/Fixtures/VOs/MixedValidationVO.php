<?php

// tests/Fixtures/VOs/MixedValidationVO.php

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\StringType;

final readonly class MixedValidationVO extends GraniteVO
{
    public function __construct(
        #[Required]  // Attribute validation
        #[StringType]
        public string $title,
        public string $content,  // Method validation
        public array $tags,      // Method validation
        public string $status,    // Method validation
    ) {}

    protected static function rules(): array
    {
        return [
            // Method-based rules (should override attributes if both exist)
            'content' => 'required|string|min:10',
            'tags' => 'array|max:5',
            'status' => 'required|in:draft,published,archived',
        ];
    }
}

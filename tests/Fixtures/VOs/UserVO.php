<?php

// tests/Fixtures/VOs/UserVO.php

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Max;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\StringType;

final readonly class UserVO extends GraniteVO
{
    public function __construct(
        #[Required]
        #[StringType]
        public string $name,
        #[Required]
        #[Email]
        public string $email,
        #[Min(18)]
        #[Max(120)]
        public ?int $age = null,
    ) {}
}

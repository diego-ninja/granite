<?php

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Max;
use Ninja\Granite\Validation\Attributes\StringType;

final readonly class ValidatedUserVO extends GraniteVO
{
    public function __construct(
        #[Required(message: "Please provide a name")]
        #[StringType]
        #[Min(2, message: "Name must be at least 2 characters")]
        public string $name,

        #[Required(message: "Email is required")]
        #[Email(message: "Please provide a valid email address")]
        public string $email,

        #[Min(18, message: "You must be at least 18 years old")]
        #[Max(120, message: "Please enter a valid age")]
        public ?int $age = null
    ) {}
}
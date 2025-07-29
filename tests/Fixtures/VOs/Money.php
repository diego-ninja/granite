<?php

// tests/Fixtures/ValueObjects/Money.php

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\StringType;

final readonly class Money extends GraniteVO
{
    public function __construct(
        #[Required]
        #[Min(0)]
        public float $amount,
        #[Required]
        #[StringType]
        public string $currency = 'USD',
    ) {}

    public function format(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }
}

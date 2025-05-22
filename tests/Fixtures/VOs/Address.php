<?php
// tests/Fixtures/ValueObjects/Address.php

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\StringType;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class Address extends GraniteVO
{
    public function __construct(
        #[Required]
        #[StringType]
        public string $street,

        #[Required]
        #[StringType]
        public string $city,

        #[Required]
        #[StringType]
        public string $country,

        #[Required]
        #[StringType]
        #[SerializedName('postal_code')]
        public string $zipCode
    ) {}

    public function fullAddress(): string
    {
        return "{$this->street}, {$this->city}, {$this->country} {$this->zipCode}";
    }
}
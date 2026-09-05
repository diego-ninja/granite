<?php

// ABOUTME: Fixture with a public readonly property declared by a parent class.
// ABOUTME: Covers constructor hydration when aliases and typed conversions cross inheritance.

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use DateTimeInterface;
use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Tests\Fixtures\Enums\UserStatus;

abstract readonly class InheritedReadonlyBase extends GraniteDTO
{
    public string $name;

    protected function initializeName(string $name): void
    {
        $this->name = $name;
    }
}

final readonly class InheritedReadonlyDTO extends InheritedReadonlyBase
{
    public function __construct(
        string $name,
        #[SerializedName('status_value')]
        public UserStatus $status,
        public DateTimeInterface $createdAt,
        public ?string $description = null,
    ) {
        $this->initializeName($name);
    }
}

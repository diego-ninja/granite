<?php

declare(strict_types=1);

namespace Tests\Fixtures\Core\ObjectFactory;

// DTO without an explicit constructor, relying on public property hydration
class EmptyDTO
{
    public string $name;
    public int $value;
    public ?string $optional = null;
}

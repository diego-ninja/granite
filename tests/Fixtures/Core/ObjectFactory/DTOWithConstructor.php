<?php

declare(strict_types=1);

namespace Tests\Fixtures\Core\ObjectFactory;

class DTOWithConstructor
{
    public string $name;
    public int $value;

    public function __construct(string $name, int $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
}

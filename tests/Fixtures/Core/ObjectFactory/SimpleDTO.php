<?php

declare(strict_types=1);

namespace Tests\Fixtures\Core\ObjectFactory;

class SimpleDTO
{
    public string $name;
    public int $value; // This will be a public property not set by constructor in one test
    public ?string $description = null;


    // Constructor for most tests
    public function __construct(string $name, ?int $valueFromConstructor = null)
    {
        $this->name = $name;
        if ($valueFromConstructor !== null) {
            $this->value = $valueFromConstructor;
        }
        // $this->value might be set by populate if not in constructor
    }
}

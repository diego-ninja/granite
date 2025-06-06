<?php

declare(strict_types=1);

namespace Tests\Fixtures\Core\ObjectFactory;

// For this test, we need a GraniteDTO to test the ::from path if ObjectFactory explicitly calls it.
// However, the goal is to test ObjectFactory's reflection path.
// So, this DTO should NOT extend GraniteDTO to force reflection path.
// If it's readonly, its properties must be constructor-promoted or initialized in constructor.
readonly class DTOWithReadonly
{
    public string $id; // readonly is on the class
    public string $name;
    public ?string $description;

    public function __construct(string $id, string $name, ?string $description = "readonly_default")
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
    }
}

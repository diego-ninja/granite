<?php

declare(strict_types=1);

namespace Tests\Fixtures\Core\ObjectFactory;

class DTOWithDefaultsAndNullables
{
    public string $id;
    public string $type;
    public ?string $description;
    public int $priority;
    public bool $active;
    public float $score;
    public array $tags;

    public function __construct(
        string $id,
        string $type = 'default_type',
        ?string $description = 'default_desc', // Making it default to test if null from data overrides it
        int $priority = 10,
        bool $active = true,
        float $score = 1.0,
        array $tags = ['default_tag']
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->description = $description;
        $this->priority = $priority;
        $this->active = $active;
        $this->score = $score;
        $this->tags = $tags;
    }
}

<?php

namespace Tests\Fixtures\Preloader;

use Ninja\Granite\GraniteDTO;

readonly class DirectPreloadSourceDTO extends GraniteDTO
{
    public string $data;

    // Add constructor for readonly property
    public function __construct(string $data)
    {
        $this->data = $data;
    }
}

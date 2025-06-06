<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mapping;

class DestinationDTO
{
    public string $destProp1;
    public string $destProp2;
    public string $common;
    public string $customDestProp;
    public string $destOnlyProp;
    public string $destForSourceOnly; // For reverse mapping test
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mapping;

class SourceDTO
{
    public string $sourceProp1;
    public string $sourceProp2;
    public string $common;
    public string $sourceForDestOnly;
    public string $srcProp; // For reverse mapping test
}

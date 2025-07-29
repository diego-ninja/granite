<?php

namespace Tests\Fixtures\Automapper;

use Ninja\Granite\Mapping\Contracts\Transformer;

class TestTransformer implements Transformer
{
    public function transform(mixed $value, array $sourceData = []): string
    {
        return 'TRANSFORMED: ' . $value;
    }
}

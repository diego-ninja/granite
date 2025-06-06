<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mapping;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\Transformer;

class MyTestTransformer implements Transformer
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function transform(mixed $value, array $sourceData = []): mixed
    {
        // Dummy transform logic
        if (is_string($value)) {
            return strtoupper($value . (isset($sourceData['append']) ? $sourceData['append'] : ''));
        }
        return $value;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}

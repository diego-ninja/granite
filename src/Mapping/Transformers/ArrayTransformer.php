<?php

namespace Ninja\Granite\Mapping\Transformers;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\Transformer;

final readonly class ArrayTransformer implements Transformer
{
    public function __construct(
        private Mapper $mapper,
        private string $destinationType
    ) {}

    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return $this->mapper->mapArray($value, $this->destinationType);
    }
}
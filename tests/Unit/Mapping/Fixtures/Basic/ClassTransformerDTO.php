<?php

namespace Tests\Unit\Mapping\Fixtures\Basic;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\Contracts\Transformer;

final readonly class ClassTransformerDTO extends GraniteDTO
{
    public function __construct(
        #[MapWith(CustomTransformer::class)]
        public string $value,
    ) {}
}

class CustomTransformer implements Transformer
{
    public function transform(mixed $value, array $sourceData = []): string
    {
        return mb_strtoupper($value) . '_TRANSFORMED';
    }
}

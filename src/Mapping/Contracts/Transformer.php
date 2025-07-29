<?php

namespace Ninja\Granite\Mapping\Contracts;

interface Transformer
{
    /**
     * Transform value.
     *
     * @param mixed $value Source value
     * @param array $sourceData Complete source data for context
     * @return mixed Transformed value
     */
    public function transform(mixed $value, array $sourceData = []): mixed;
}

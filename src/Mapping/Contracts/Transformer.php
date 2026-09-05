<?php
// ABOUTME: Defines Transformer as part of the object mapping pipeline.
// ABOUTME: Owns the Transformer boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Contracts;

interface Transformer
{
    /**
     * Transform value.
     *
     * @param mixed $value Source value
     * @param array<array-key, mixed> $sourceData Complete source data for context
     * @return mixed Transformed value
     */
    public function transform(mixed $value, array $sourceData = []): mixed;
}

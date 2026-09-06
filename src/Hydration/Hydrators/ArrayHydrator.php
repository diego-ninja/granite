<?php
// ABOUTME: Defines ArrayHydrator as part of the input hydration and normalization pipeline.
// ABOUTME: Owns the ArrayHydrator boundary between external input and typed objects.

namespace Ninja\Granite\Hydration\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;

/**
 * Hydrator for array data.
 * Simply returns the array as-is since it's already in the correct format.
 */
class ArrayHydrator extends AbstractHydrator
{
    protected int $priority = 80;

    public function supports(mixed $data, string $targetClass): bool
    {
        return is_array($data);
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var array<array-key, mixed> $data */
        return $data;
    }
}

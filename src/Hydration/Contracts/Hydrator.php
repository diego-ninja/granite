<?php

namespace Ninja\Granite\Hydration\Contracts;

/**
 * Interface for data hydrators.
 *
 * Hydrators are responsible for converting various data formats
 * (arrays, JSON, objects, etc.) into normalized array format that
 * can be used to populate Granite objects.
 */
interface Hydrator
{
    /**
     * Check if this hydrator can handle the given data.
     *
     * @param mixed $data Data to check
     * @param string $targetClass Target class being hydrated
     * @return bool True if this hydrator supports the data type
     */
    public function supports(mixed $data, string $targetClass): bool;

    /**
     * Extract data from the source and convert to normalized array.
     *
     * @param mixed $data Source data
     * @param string $targetClass Target class being hydrated
     * @return array Normalized data as associative array
     */
    public function hydrate(mixed $data, string $targetClass): array;

    /**
     * Get the priority of this hydrator.
     * Higher priority hydrators are tried first.
     *
     * @return int Priority (higher = tried first)
     */
    public function getPriority(): int;
}

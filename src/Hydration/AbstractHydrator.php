<?php

namespace Ninja\Granite\Hydration;

use Ninja\Granite\Hydration\Contracts\Hydrator;

/**
 * Base class for hydrators providing common functionality.
 */
abstract class AbstractHydrator implements Hydrator
{
    /**
     * Default priority for hydrators.
     */
    protected int $priority = 50;

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Validate that the result is a valid array.
     *
     * @param mixed $result Result from extraction
     * @return array Valid array or empty array
     */
    protected function ensureArray(mixed $result): array
    {
        return is_array($result) ? $result : [];
    }
}

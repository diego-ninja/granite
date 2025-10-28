<?php

namespace Ninja\Granite\Hydration\Hydrators;

use InvalidArgumentException;
use Ninja\Granite\Hydration\AbstractHydrator;

/**
 * Catch-all hydrator for plain strings.
 * Since strings that are not JSON cannot be hydrated,
 * this hydrator exists to provide a better error message.
 */
class StringHydrator extends AbstractHydrator
{
    protected int $priority = 10; // Very low priority, catch-all

    public function supports(mixed $data, string $targetClass): bool
    {
        // Support any string (but will be tried last due to low priority)
        return is_string($data);
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        // All strings should be JSON if they're being used for hydration
        // If we reach here, it means the string looked like JSON but was invalid
        throw new InvalidArgumentException('Invalid JSON string provided');
    }
}

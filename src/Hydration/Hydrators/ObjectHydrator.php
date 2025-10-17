<?php

namespace Ninja\Granite\Hydration\Hydrators;

use JsonSerializable;
use Ninja\Granite\Hydration\AbstractHydrator;

/**
 * Hydrator for generic objects (Phase 1).
 *
 * Extraction strategies (in order):
 * 1. If has toArray() method - use it
 * 2. If implements JsonSerializable - use jsonSerialize()
 * 3. Extract public properties using get_object_vars()
 */
class ObjectHydrator extends AbstractHydrator
{
    protected int $priority = 70;

    public function supports(mixed $data, string $targetClass): bool
    {
        return is_object($data);
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var object $data */
        // Strategy 1: If object has toArray() method, use it
        if (method_exists($data, 'toArray')) {
            $result = $data->toArray();
            return $this->ensureArray($result);
        }

        // Strategy 2: If object implements JsonSerializable, use jsonSerialize()
        if ($data instanceof JsonSerializable) {
            $result = $data->jsonSerialize();
            return $this->ensureArray($result);
        }

        // Strategy 3: Extract public properties
        // get_object_vars() returns only accessible properties (public from outside context)
        return get_object_vars($data);
    }
}

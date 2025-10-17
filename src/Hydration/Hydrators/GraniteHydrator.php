<?php

namespace Ninja\Granite\Hydration\Hydrators;

use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Hydration\AbstractHydrator;

/**
 * Hydrator for Granite objects.
 * Uses the object's array() method to extract data.
 */
class GraniteHydrator extends AbstractHydrator
{
    protected int $priority = 100;

    public function supports(mixed $data, string $targetClass): bool
    {
        return $data instanceof GraniteObject;
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var GraniteObject $data */
        return $data->array();
    }
}

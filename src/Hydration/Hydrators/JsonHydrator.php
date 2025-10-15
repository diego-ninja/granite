<?php

namespace Ninja\Granite\Hydration\Hydrators;

use InvalidArgumentException;
use Ninja\Granite\Hydration\AbstractHydrator;

/**
 * Hydrator for JSON string data.
 * Decodes JSON strings into associative arrays.
 */
class JsonHydrator extends AbstractHydrator
{
    protected int $priority = 90;

    public function supports(mixed $data, string $targetClass): bool
    {
        if ( ! is_string($data)) {
            return false;
        }

        // Quick check: JSON strings typically start with { or [
        // This hydrator claims support for any string that looks like JSON
        // (validation happens in hydrate() method)
        $trimmed = trim($data);
        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var string $data */
        // Validate the JSON string
        if ( ! json_validate($data)) {
            throw new InvalidArgumentException('Invalid JSON string provided');
        }

        $decoded = json_decode($data, true);
        return $this->ensureArray($decoded);
    }
}

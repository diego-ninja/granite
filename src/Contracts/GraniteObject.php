<?php
// ABOUTME: Defines GraniteObject as part of the public library contracts.
// ABOUTME: Owns the GraniteObject boundary within the public library contracts.

namespace Ninja\Granite\Contracts;

interface GraniteObject
{
    /**
     * Create a new instance from various data sources.
     *
     * @param mixed ...$args Variable arguments supporting multiple patterns
     * @return static New instance
     */
    public static function from(mixed ...$args): static;

    /**
     * Convert the object to an array.
     *
     * @return array<array-key, mixed> Array representation
     */
    public function array(): array;

    /**
     * Convert the object to a JSON string.
     *
     * @return string JSON representation
     */
    public function json(): string;
}

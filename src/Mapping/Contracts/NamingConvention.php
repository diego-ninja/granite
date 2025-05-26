<?php

namespace Ninja\Granite\Mapping\Contracts;

interface NamingConvention
{
    /**
     * Get the convention name.
     */
    public function getName(): string;
    
    /**
     * Determines if a string follows this convention.
     */
    public function matches(string $name): bool;
    
    /**
     * Converts a name from this convention to the normalized form.
     */
    public function normalize(string $name): string;
    
    /**
     * Converts a normalized name to this convention.
     */
    public function denormalize(string $normalized): string;
    
    /**
     * Calculates the confidence that two names represent the same property.
     * Returns a value between 0.0 (no match) and 1.0 (perfect match).
     */
    public function calculateMatchConfidence(string $sourceName, string $destinationName): float;
}

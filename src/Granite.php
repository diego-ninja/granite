<?php
// ABOUTME: Defines Granite as part of the Granite runtime.
// ABOUTME: Owns the Granite boundary within the Granite runtime.

namespace Ninja\Granite;

use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Support\ObjectState;
use Ninja\Granite\Traits\HasCarbonSupport;
use Ninja\Granite\Traits\HasComparison;
use Ninja\Granite\Traits\HasDeserialization;
use Ninja\Granite\Traits\HasNamingConventions;
use Ninja\Granite\Traits\HasSerialization;
use Ninja\Granite\Traits\HasTypeConversion;
use Ninja\Granite\Traits\HasValidation;

/**
 * Abstract base class for Granite objects.
 *
 * Granite objects are immutable data transfer objects with optional validation,
 * automatic type conversion, serialization/deserialization capabilities, and
 * extensive Carbon date library support.
 *
 * Features:
 * - Automatic conversion from arrays, JSON, and other Granite objects
 * - Type-safe property mapping with enum, DateTime, and Carbon support
 * - Flexible naming conventions for property serialization
 * - Optional validation using attributes
 * - Carbon date library integration with timezone and format support
 * - Immutable readonly properties
 *
 * @since 2.0.0 Unified Granite object (replaces separate DTO/ValueObject concepts)
 */
abstract readonly class Granite implements GraniteObject
{
    use HasCarbonSupport;
    use HasComparison;
    use HasDeserialization;
    use HasNamingConventions;
    use HasSerialization;
    use HasTypeConversion;
    use HasValidation;

    /**
     * Create a new instance with selected PHP properties replaced.
     *
     * @param array<array-key, mixed> $modifications
     */
    public function with(array $modifications): static
    {
        return static::from(array_replace(ObjectState::extract($this), $modifications));
    }
}

<?php

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;
use DateTimeInterface;
use Ninja\Granite\Transformers\CarbonTransformer;

/**
 * Attribute to configure Carbon date/time handling for a property.
 * Provides fine-grained control over Carbon parsing and serialization.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CarbonDate
{
    /**
     * Constructor.
     *
     * @param string|null $format Format for parsing input (e.g., 'Y-m-d H:i:s')
     * @param string|null $timezone Timezone for the Carbon instance
     * @param string|null $locale Locale for Carbon instance
     * @param bool $immutable Whether to use CarbonImmutable instead of Carbon
     * @param bool $parseRelative Whether to enable relative parsing ("tomorrow", etc.)
     * @param string|null $serializeFormat Format for serialization output
     * @param string|null $serializeTimezone Timezone for serialization output
     * @param DateTimeInterface|string|null $min Minimum allowed date (for validation)
     * @param DateTimeInterface|string|null $max Maximum allowed date (for validation)
     */
    public function __construct(
        public ?string $format = null,
        public ?string $timezone = null,
        public ?string $locale = null,
        public bool $immutable = false,
        public bool $parseRelative = true,
        public ?string $serializeFormat = null,
        public ?string $serializeTimezone = null,
        public DateTimeInterface|string|null $min = null,
        public DateTimeInterface|string|null $max = null,
    ) {}

    /**
     * Create a transformer from this attribute configuration.
     *
     * @return CarbonTransformer Carbon transformer
     */
    public function createTransformer(): CarbonTransformer
    {
        return new CarbonTransformer(
            format: $this->format,
            timezone: $this->timezone,
            locale: $this->locale,
            immutable: $this->immutable,
            parseRelative: $this->parseRelative,
            serializeFormat: $this->serializeFormat,
            serializeTimezone: $this->serializeTimezone,
            min: $this->min,
            max: $this->max,
        );
    }
}

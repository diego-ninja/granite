<?php

namespace Ninja\Granite\Transformers;

use DateTimeInterface;
use Exception;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Support\CarbonSupport;

/**
 * Transformer for Carbon date/time objects.
 * Handles conversion to/from Carbon instances with configurable options.
 */
final readonly class CarbonTransformer implements Transformer
{
    /**
     * Constructor.
     *
     * @param string|null $format Format for parsing input
     * @param string|null $timezone Timezone for the Carbon instance
     * @param string|null $locale Locale for Carbon instance
     * @param bool $immutable Whether to use CarbonImmutable
     * @param bool $parseRelative Whether to enable relative parsing
     * @param string|null $serializeFormat Format for serialization output
     * @param string|null $serializeTimezone Timezone for serialization output
     * @param DateTimeInterface|string|null $min Minimum allowed date
     * @param DateTimeInterface|string|null $max Maximum allowed date
     */
    public function __construct(
        private ?string $format = null,
        private ?string $timezone = null,
        private ?string $locale = null,
        private bool $immutable = false,
        private bool $parseRelative = true,
        private ?string $serializeFormat = null,
        private ?string $serializeTimezone = null,
        private DateTimeInterface|string|null $min = null,
        private DateTimeInterface|string|null $max = null,
    ) {}

    /**
     * Transform value to Carbon instance.
     *
     * @param mixed $value Source value
     * @param array $sourceData Complete source data for context
     * @return DateTimeInterface|null Transformed Carbon instance
     */
    public function transform(mixed $value, array $sourceData = []): ?DateTimeInterface
    {
        if (null === $value) {
            return null;
        }

        // If Carbon is not available, return null
        if ( ! CarbonSupport::isAvailable()) {
            return null;
        }

        try {
            // Create Carbon instance with configuration
            $carbon = $this->createCarbonInstance($value);

            if (null === $carbon) {
                return null;
            }

            // Apply locale if specified and Carbon supports it
            if (null !== $this->locale && CarbonSupport::isCarbonInstance($carbon)) {
                if ($carbon instanceof \Carbon\Carbon) {
                    $carbon = $carbon->locale($this->locale);
                } elseif ($carbon instanceof \Carbon\CarbonImmutable) {
                    $carbon = $carbon->locale($this->locale);
                }
            }

            // Validate range if specified
            if ($carbon instanceof DateTimeInterface && ! $this->isWithinRange($carbon)) {
                return null;
            }

            /** @var DateTimeInterface $carbon */
            return $carbon;

        } catch (Exception) {
            return null;
        }
    }

    /**
     * Serialize Carbon instance to string.
     *
     * @param DateTimeInterface|null $carbon Carbon instance to serialize
     * @return string|null Serialized string
     */
    public function serialize(?DateTimeInterface $carbon): ?string
    {
        if (null === $carbon) {
            return null;
        }

        return CarbonSupport::serialize(
            $carbon,
            $this->getEffectiveSerializeFormat(),
            $this->serializeTimezone,
        );
    }

    // =============================================================================
    // Getters for introspection
    // =============================================================================

    /**
     * Get the configured format.
     *
     * @return string|null Format string
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * Get the configured timezone.
     *
     * @return string|null Timezone string
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Get the configured locale.
     *
     * @return string|null Locale string
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Check if immutable Carbon is preferred.
     *
     * @return bool True if immutable
     */
    public function isImmutable(): bool
    {
        return $this->immutable;
    }

    /**
     * Check if relative parsing is enabled.
     *
     * @return bool True if relative parsing is enabled
     */
    public function isParseRelativeEnabled(): bool
    {
        return $this->parseRelative;
    }

    /**
     * Get serialization format.
     *
     * @return string|null Serialization format
     */
    public function getSerializeFormat(): ?string
    {
        return $this->serializeFormat;
    }

    /**
     * Get serialization timezone.
     *
     * @return string|null Serialization timezone
     */
    public function getSerializeTimezone(): ?string
    {
        return $this->serializeTimezone;
    }

    /**
     * Get minimum allowed date.
     *
     * @return DateTimeInterface|string|null Minimum date
     */
    public function getMin(): DateTimeInterface|string|null
    {
        return $this->min;
    }

    /**
     * Get maximum allowed date.
     *
     * @return DateTimeInterface|string|null Maximum date
     */
    public function getMax(): DateTimeInterface|string|null
    {
        return $this->max;
    }

    /**
     * Create Carbon instance from various input types.
     *
     * @param mixed $value Input value
     * @return DateTimeInterface|null Carbon instance
     */
    private function createCarbonInstance(mixed $value): ?DateTimeInterface
    {
        // Handle relative parsing if disabled
        if ( ! $this->parseRelative && is_string($value) && $this->isRelativeString($value)) {
            return null;
        }

        // Get effective timezone (attribute > global config > null)
        $timezone = $this->timezone ?? GraniteConfig::getInstance()->getCarbonTimezone() ?? 'UTC';

        // Get effective format (attribute > global config > null)
        $format = $this->format ?? GraniteConfig::getInstance()->getCarbonParseFormat() ?? null;

        return CarbonSupport::create(
            $value,
            $format,
            $timezone,
            $this->immutable,
        );
    }

    /**
     * Check if a string appears to be a relative date string.
     *
     * @param string $value String to check
     * @return bool True if it looks like a relative date
     */
    private function isRelativeString(string $value): bool
    {
        $relativeWords = [
            'now', 'today', 'tomorrow', 'yesterday',
            'next', 'last', 'this', 'ago',
            'week', 'month', 'year', 'day',
            'hour', 'minute', 'second',
            '+', '-',
        ];

        $lowerValue = strtolower($value);

        foreach ($relativeWords as $word) {
            if (str_contains($lowerValue, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Carbon instance is within specified range.
     *
     * @param DateTimeInterface $carbon Carbon instance to check
     * @return bool True if within range
     */
    private function isWithinRange(DateTimeInterface $carbon): bool
    {
        if (null !== $this->min) {
            $minDate = $this->parseRangeDate($this->min);
            if (null !== $minDate && $carbon < $minDate) {
                return false;
            }
        }

        if (null !== $this->max) {
            $maxDate = $this->parseRangeDate($this->max);
            if (null !== $maxDate && $carbon > $maxDate) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse range date (min/max) to DateTimeInterface.
     *
     * @param DateTimeInterface|string $date Date to parse
     * @return DateTimeInterface|null Parsed date
     */
    private function parseRangeDate(DateTimeInterface|string $date): ?DateTimeInterface
    {
        if ($date instanceof DateTimeInterface) {
            return $date;
        }

        return CarbonSupport::create($date, null, $this->timezone, $this->immutable);
    }

    /**
     * Get effective serialization format.
     *
     * @return string Serialization format
     */
    private function getEffectiveSerializeFormat(): string
    {
        return $this->serializeFormat
            ?? GraniteConfig::getInstance()->getCarbonSerializeFormat()
            ?? DateTimeInterface::ATOM;
    }
}

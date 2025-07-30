<?php

namespace Ninja\Granite\Config;

use Ninja\Granite\Support\CarbonSupport;

/**
 * Global configuration for Granite library.
 * Provides centralized configuration for Carbon support and other global settings.
 */
final class GraniteConfig
{
    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Whether to prefer Carbon over native DateTime.
     */
    private bool $preferCarbon = false;

    /**
     * Whether to prefer CarbonImmutable over Carbon.
     */
    private bool $preferCarbonImmutable = false;

    /**
     * Default timezone for Carbon instances.
     */
    private ?string $carbonTimezone = null;

    /**
     * Default locale for Carbon instances.
     */
    private ?string $carbonLocale = null;

    /**
     * Default format for Carbon parsing.
     */
    private ?string $carbonParseFormat = null;

    /**
     * Default format for Carbon serialization.
     */
    private ?string $carbonSerializeFormat = null;

    /**
     * Default timezone for Carbon serialization.
     */
    private ?string $carbonSerializeTimezone = null;

    /**
     * Whether to enable relative parsing (e.g., "tomorrow", "last week").
     */
    private bool $carbonParseRelative = true;

    /**
     * Private constructor to enforce singleton.
     */
    private function __construct() {}

    /**
     * Get the singleton instance.
     *
     * @return self Configuration instance
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset configuration to defaults (useful for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // =============================================================================
    // Static Convenience Methods
    // =============================================================================

    /**
     * Static method to configure Carbon preferences.
     *
     * @param bool $preferCarbon Whether to prefer Carbon
     * @param bool $preferImmutable Whether to prefer CarbonImmutable
     * @param string|null $timezone Default timezone
     * @return self Configuration instance
     */
    public static function configureCarbon(
        bool $preferCarbon = true,
        bool $preferImmutable = false,
        ?string $timezone = null,
    ): self {
        return self::getInstance()
            ->preferCarbon($preferCarbon)
            ->preferCarbonImmutable($preferImmutable)
            ->carbonTimezone($timezone);
    }

    // =============================================================================
    // Carbon Configuration Methods
    // =============================================================================

    /**
     * Set whether to prefer Carbon over native DateTime.
     *
     * @param bool $prefer Whether to prefer Carbon
     * @return self For method chaining
     */
    public function preferCarbon(bool $prefer = true): self
    {
        $this->preferCarbon = $prefer;
        return $this;
    }

    /**
     * Set whether to prefer CarbonImmutable over Carbon.
     *
     * @param bool $prefer Whether to prefer CarbonImmutable
     * @return self For method chaining
     */
    public function preferCarbonImmutable(bool $prefer = true): self
    {
        $this->preferCarbonImmutable = $prefer;
        return $this;
    }

    /**
     * Set default timezone for Carbon instances.
     *
     * @param string|null $timezone Timezone name (e.g., 'UTC', 'America/New_York')
     * @return self For method chaining
     */
    public function carbonTimezone(?string $timezone): self
    {
        $this->carbonTimezone = $timezone;
        return $this;
    }

    /**
     * Set default locale for Carbon instances.
     *
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return self For method chaining
     */
    public function carbonLocale(?string $locale): self
    {
        $this->carbonLocale = $locale;
        return $this;
    }

    /**
     * Set default format for Carbon parsing.
     *
     * @param string|null $format Format string (e.g., 'Y-m-d H:i:s')
     * @return self For method chaining
     */
    public function carbonParseFormat(?string $format): self
    {
        $this->carbonParseFormat = $format;
        return $this;
    }

    /**
     * Set default format for Carbon serialization.
     *
     * @param string|null $format Format string (e.g., 'c', 'Y-m-d H:i:s')
     * @return self For method chaining
     */
    public function carbonSerializeFormat(?string $format): self
    {
        $this->carbonSerializeFormat = $format;
        return $this;
    }

    /**
     * Set default timezone for Carbon serialization.
     *
     * @param string|null $timezone Timezone for serialization output
     * @return self For method chaining
     */
    public function carbonSerializeTimezone(?string $timezone): self
    {
        $this->carbonSerializeTimezone = $timezone;
        return $this;
    }

    /**
     * Set whether to enable relative parsing.
     *
     * @param bool $enable Whether to enable relative parsing
     * @return self For method chaining
     */
    public function carbonParseRelative(bool $enable = true): self
    {
        $this->carbonParseRelative = $enable;
        return $this;
    }

    // =============================================================================
    // Configuration Presets
    // =============================================================================

    /**
     * Configure for Laravel-style Carbon usage.
     *
     * @return self For method chaining
     */
    public function useLaravelDefaults(): self
    {
        return $this->preferCarbon(true)
            ->preferCarbonImmutable(false)
            ->carbonTimezone('UTC')
            ->carbonSerializeFormat('c')
            ->carbonParseRelative(true);
    }

    /**
     * Configure for API-friendly usage with immutable dates.
     *
     * @return self For method chaining
     */
    public function useApiDefaults(): self
    {
        return $this->preferCarbon(true)
            ->preferCarbonImmutable(true)
            ->carbonTimezone('UTC')
            ->carbonSerializeFormat('c')
            ->carbonSerializeTimezone('UTC')
            ->carbonParseRelative(false);
    }

    /**
     * Configure for strict ISO 8601 compliance.
     *
     * @return self For method chaining
     */
    public function useStrictDefaults(): self
    {
        return $this->preferCarbon(true)
            ->preferCarbonImmutable(true)
            ->carbonTimezone('UTC')
            ->carbonParseFormat('c')
            ->carbonSerializeFormat('c')
            ->carbonSerializeTimezone('UTC')
            ->carbonParseRelative(false);
    }

    // =============================================================================
    // Getters
    // =============================================================================

    /**
     * Check if Carbon is preferred over native DateTime.
     *
     * @return bool True if Carbon is preferred
     */
    public function shouldPreferCarbon(): bool
    {
        return $this->preferCarbon && CarbonSupport::isAvailable();
    }

    /**
     * Check if CarbonImmutable is preferred over Carbon.
     *
     * @return bool True if CarbonImmutable is preferred
     */
    public function shouldPreferCarbonImmutable(): bool
    {
        return $this->preferCarbonImmutable && CarbonSupport::isImmutableAvailable();
    }

    /**
     * Get the preferred DateTime class based on configuration.
     *
     * @return string Preferred DateTime class name
     */
    public function getPreferredDateTimeClass(): string
    {
        if ($this->shouldPreferCarbon()) {
            if ($this->shouldPreferCarbonImmutable()) {
                return 'Carbon\CarbonImmutable';
            }
            return 'Carbon\Carbon';
        }

        return 'DateTimeImmutable';
    }

    /**
     * Get default timezone for Carbon.
     *
     * @return string|null Timezone name
     */
    public function getCarbonTimezone(): ?string
    {
        return $this->carbonTimezone;
    }

    /**
     * Get default locale for Carbon.
     *
     * @return string|null Locale code
     */
    public function getCarbonLocale(): ?string
    {
        return $this->carbonLocale;
    }

    /**
     * Get default format for Carbon parsing.
     *
     * @return string|null Parse format
     */
    public function getCarbonParseFormat(): ?string
    {
        return $this->carbonParseFormat;
    }

    /**
     * Get default format for Carbon serialization.
     *
     * @return string|null Serialize format
     */
    public function getCarbonSerializeFormat(): ?string
    {
        return $this->carbonSerializeFormat;
    }

    /**
     * Get default timezone for Carbon serialization.
     *
     * @return string|null Serialize timezone
     */
    public function getCarbonSerializeTimezone(): ?string
    {
        return $this->carbonSerializeTimezone;
    }

    /**
     * Check if relative parsing is enabled.
     *
     * @return bool True if relative parsing is enabled
     */
    public function isCarbonParseRelativeEnabled(): bool
    {
        return $this->carbonParseRelative;
    }

    /**
     * Check if the configuration suggests using Carbon for auto-conversion.
     *
     * @param string $typeName The type name being converted to
     * @return bool True if Carbon should be used for auto-conversion
     */
    public function shouldAutoConvertToCarbon(string $typeName): bool
    {
        // Don't auto-convert if Carbon isn't preferred
        if ( ! $this->shouldPreferCarbon()) {
            return false;
        }

        // Don't auto-convert if explicitly requesting native DateTime
        if ('DateTime' === $typeName || 'DateTimeImmutable' === $typeName) {
            return false;
        }

        // Auto-convert DateTimeInterface to Carbon if preferred
        return 'DateTimeInterface' === $typeName;
    }
}

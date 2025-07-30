<?php

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;

/**
 * Class-level attribute to configure Carbon as the default DateTime provider.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DateTimeProvider
{
    /**
     * Constructor.
     *
     * @param string $provider Provider class name ('Carbon\Carbon', 'Carbon\CarbonImmutable', etc.)
     * @param string|null $timezone Default timezone for all DateTime properties
     * @param string|null $locale Default locale for all DateTime properties
     * @param string|null $format Default format for all DateTime properties
     * @param string|null $serializeFormat Default serialization format
     * @param bool $parseRelative Whether to enable relative parsing by default
     */
    public function __construct(
        public string $provider,
        public ?string $timezone = null,
        public ?string $locale = null,
        public ?string $format = null,
        public ?string $serializeFormat = null,
        public bool $parseRelative = true,
    ) {}

    /**
     * Check if this provider is Carbon.
     *
     * @return bool True if provider is Carbon
     */
    public function isCarbon(): bool
    {
        return 'Carbon\Carbon' === $this->provider;
    }

    /**
     * Check if this provider is CarbonImmutable.
     *
     * @return bool True if provider is CarbonImmutable
     */
    public function isCarbonImmutable(): bool
    {
        return 'Carbon\CarbonImmutable' === $this->provider;
    }

    /**
     * Check if this provider is any Carbon variant.
     *
     * @return bool True if provider is any Carbon class
     */
    public function isCarbonProvider(): bool
    {
        return $this->isCarbon() || $this->isCarbonImmutable();
    }
}

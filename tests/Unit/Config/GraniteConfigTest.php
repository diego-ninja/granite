<?php

namespace Tests\Unit\Config;

use Ninja\Granite\Config\GraniteConfig;
use Tests\Helpers\TestCase;

class GraniteConfigTest extends TestCase
{
    private GraniteConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = GraniteConfig::getInstance();
        $this->resetConfig();
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        parent::tearDown();
    }

    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = GraniteConfig::getInstance();
        $instance2 = GraniteConfig::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_prefer_carbon_default_false(): void
    {
        $this->assertFalse($this->config->shouldPreferCarbon());
    }

    public function test_prefer_carbon_can_be_enabled(): void
    {
        $result = $this->config->preferCarbon(true);

        $this->assertTrue($this->config->shouldPreferCarbon());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_prefer_carbon_can_be_disabled(): void
    {
        $this->config->preferCarbon(true);
        $this->config->preferCarbon(false);

        $this->assertFalse($this->config->shouldPreferCarbon());
    }

    public function test_prefer_carbon_immutable_default_false(): void
    {
        $this->assertFalse($this->config->shouldPreferCarbonImmutable());
    }

    public function test_prefer_carbon_immutable_can_be_enabled(): void
    {
        $result = $this->config->preferCarbonImmutable(true);

        $this->assertTrue($this->config->shouldPreferCarbonImmutable());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_carbon_timezone_default_null(): void
    {
        $this->assertNull($this->config->getCarbonTimezone());
    }

    public function test_carbon_timezone_can_be_set(): void
    {
        $result = $this->config->carbonTimezone('America/New_York');

        $this->assertEquals('America/New_York', $this->config->getCarbonTimezone());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_carbon_locale_default_null(): void
    {
        $this->assertNull($this->config->getCarbonLocale());
    }

    public function test_carbon_locale_can_be_set(): void
    {
        $result = $this->config->carbonLocale('en_US');

        $this->assertEquals('en_US', $this->config->getCarbonLocale());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_carbon_parse_format_default_null(): void
    {
        $this->assertNull($this->config->getCarbonParseFormat());
    }

    public function test_carbon_parse_format_can_be_set(): void
    {
        $result = $this->config->carbonParseFormat('Y-m-d H:i:s');

        $this->assertEquals('Y-m-d H:i:s', $this->config->getCarbonParseFormat());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_carbon_serialize_format_default_null(): void
    {
        $this->assertNull($this->config->getCarbonSerializeFormat());
    }

    public function test_carbon_serialize_format_can_be_set(): void
    {
        $result = $this->config->carbonSerializeFormat('c');

        $this->assertEquals('c', $this->config->getCarbonSerializeFormat());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_carbon_serialize_timezone_default_null(): void
    {
        $this->assertNull($this->config->getCarbonSerializeTimezone());
    }

    public function test_carbon_serialize_timezone_can_be_set(): void
    {
        $result = $this->config->carbonSerializeTimezone('UTC');

        $this->assertEquals('UTC', $this->config->getCarbonSerializeTimezone());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_carbon_parse_relative_default_false(): void
    {
        $this->assertFalse($this->config->isCarbonParseRelativeEnabled());
    }

    public function test_carbon_parse_relative_can_be_enabled(): void
    {
        $result = $this->config->carbonParseRelative(true);

        $this->assertTrue($this->config->isCarbonParseRelativeEnabled());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_use_laravel_defaults(): void
    {
        $result = $this->config->useLaravelDefaults();

        $this->assertTrue($this->config->shouldPreferCarbon());
        $this->assertFalse($this->config->shouldPreferCarbonImmutable());
        $this->assertTrue($this->config->isCarbonParseRelativeEnabled());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_use_api_defaults(): void
    {
        $result = $this->config->useApiDefaults();

        $this->assertTrue($this->config->shouldPreferCarbon());
        $this->assertTrue($this->config->shouldPreferCarbonImmutable());
        $this->assertEquals('UTC', $this->config->getCarbonTimezone());
        $this->assertEquals('c', $this->config->getCarbonSerializeFormat());
        $this->assertEquals('UTC', $this->config->getCarbonSerializeTimezone());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_use_strict_defaults(): void
    {
        $result = $this->config->useStrictDefaults();

        // useStrictDefaults actually sets up Carbon preferences in strict mode
        $this->assertTrue($this->config->shouldPreferCarbon());
        $this->assertTrue($this->config->shouldPreferCarbonImmutable());
        $this->assertFalse($this->config->isCarbonParseRelativeEnabled());
        $this->assertEquals('UTC', $this->config->getCarbonTimezone());
        $this->assertEquals('c', $this->config->getCarbonParseFormat());
        $this->assertSame($this->config, $result); // Fluent interface
    }

    public function test_get_preferred_datetime_class_default(): void
    {
        $this->assertEquals('DateTimeImmutable', $this->config->getPreferredDateTimeClass());
    }

    public function test_get_preferred_datetime_class_with_carbon(): void
    {
        $this->config->preferCarbon(true);

        $expected = 'Carbon\\Carbon';
        $this->assertEquals($expected, $this->config->getPreferredDateTimeClass());
    }

    public function test_get_preferred_datetime_class_with_carbon_immutable(): void
    {
        $this->config->preferCarbon(true)->preferCarbonImmutable(true);

        $expected = 'Carbon\\CarbonImmutable';
        $this->assertEquals($expected, $this->config->getPreferredDateTimeClass());
    }

    public function test_should_auto_convert_to_carbon_false_by_default(): void
    {
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('DateTime'));
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('DateTimeImmutable'));
    }

    public function test_should_auto_convert_to_carbon_with_preference(): void
    {
        $this->config->preferCarbon(true);

        // According to the implementation, DateTime and DateTimeImmutable are NOT auto-converted
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('DateTime'));
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('DateTimeImmutable'));

        // Only DateTimeInterface should be auto-converted
        $this->assertTrue($this->config->shouldAutoConvertToCarbon('DateTimeInterface'));
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('string'));
    }

    public function test_should_auto_convert_carbon_types_to_carbon(): void
    {
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('Carbon\\Carbon'));
        $this->assertFalse($this->config->shouldAutoConvertToCarbon('Carbon\\CarbonImmutable'));
    }

    public function test_chaining_multiple_configurations(): void
    {
        $result = $this->config
            ->preferCarbon(true)
            ->preferCarbonImmutable(true)
            ->carbonTimezone('Europe/London')
            ->carbonLocale('en_GB')
            ->carbonParseFormat('d/m/Y')
            ->carbonSerializeFormat('Y-m-d\TH:i:s\Z')
            ->carbonSerializeTimezone('UTC')
            ->carbonParseRelative(true);

        $this->assertSame($this->config, $result);
        $this->assertTrue($this->config->shouldPreferCarbon());
        $this->assertTrue($this->config->shouldPreferCarbonImmutable());
        $this->assertEquals('Europe/London', $this->config->getCarbonTimezone());
        $this->assertEquals('en_GB', $this->config->getCarbonLocale());
        $this->assertEquals('d/m/Y', $this->config->getCarbonParseFormat());
        $this->assertEquals('Y-m-d\TH:i:s\Z', $this->config->getCarbonSerializeFormat());
        $this->assertEquals('UTC', $this->config->getCarbonSerializeTimezone());
        $this->assertTrue($this->config->isCarbonParseRelativeEnabled());
    }

    private function resetConfig(): void
    {
        // Reset to defaults
        $this->config->preferCarbon(false)
            ->preferCarbonImmutable(false)
            ->carbonTimezone(null)
            ->carbonLocale(null)
            ->carbonParseFormat(null)
            ->carbonSerializeFormat(null)
            ->carbonSerializeTimezone(null)
            ->carbonParseRelative(false);
    }
}

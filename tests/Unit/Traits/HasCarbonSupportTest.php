<?php

namespace Tests\Unit\Traits;

use DateTimeImmutable;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Traits\HasCarbonSupport;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionProperty;
use Tests\Helpers\TestCase;

class HasCarbonSupportTest extends TestCase
{
    private TestClassWithCarbonSupport $testClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testClass = new TestClassWithCarbonSupport();
    }

    public function test_convert_to_carbon_when_carbon_not_available(): void
    {
        if ( ! CarbonSupport::isAvailable()) {
            $result = $this->testClass->testConvertToCarbon('2023-01-01', 'Carbon');
            $this->assertNull($result);
        } else {
            // Test that it works when Carbon is available
            $result = $this->testClass->testConvertToCarbon('2023-01-01', 'Carbon');
            $this->assertNotNull($result);
        }
    }

    public function test_convert_to_carbon_with_carbon_transformer(): void
    {
        if ( ! CarbonSupport::isAvailable()) {
            $this->assertTrue(true); // Skip gracefully
            return;
        }

        $property = new ReflectionProperty(TestClassWithCarbonAttribute::class, 'carbonDate');
        $result = $this->testClass->testConvertToCarbon('2023-01-01', 'Carbon', $property);

        $this->assertNotNull($result);
    }

    public function test_convert_to_carbon_fallback(): void
    {
        if ( ! CarbonSupport::isAvailable()) {
            $this->assertTrue(true); // Skip gracefully
            return;
        }

        $result = $this->testClass->testConvertToCarbon('2023-01-01', 'Carbon');
        $this->assertNotNull($result);
    }

    public function test_convert_to_datetime_with_carbon_auto_conversion(): void
    {
        $config = GraniteConfig::getInstance();

        if ($config->shouldAutoConvertToCarbon('DateTime') && CarbonSupport::isAvailable()) {
            $result = $this->testClass->testConvertToDateTime('2023-01-01', 'DateTime');
            $this->assertNotNull($result);
        } else {
            // Test basic DateTime conversion
            $result = $this->testClass->testConvertToDateTime('2023-01-01', 'DateTime');
            $this->assertNotNull($result);
        }
    }

    public function test_convert_to_datetime_with_class_provider(): void
    {
        $provider = new DateTimeProvider('Carbon\\Carbon');
        $result = $this->testClass->testConvertToDateTime('2023-01-01', 'DateTime', null, $provider);

        if (CarbonSupport::isAvailable()) {
            $this->assertNotNull($result);
        } else {
            $this->assertNull($result);
        }
    }

    public function test_convert_to_datetime_with_datetime_interface(): void
    {
        $dateTime = new DateTimeImmutable('2023-01-01');
        $result = $this->testClass->testConvertToDateTime($dateTime, 'DateTime');

        $this->assertSame($dateTime, $result);
    }

    public function test_convert_to_datetime_with_string(): void
    {
        $result = $this->testClass->testConvertToDateTime('2023-01-01', 'DateTime');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2023-01-01', $result->format('Y-m-d'));
    }

    public function test_convert_to_datetime_with_invalid_string(): void
    {
        $result = $this->testClass->testConvertToDateTime('invalid-date', 'DateTime');
        $this->assertNull($result);
    }

    public function test_convert_to_datetime_with_null(): void
    {
        $result = $this->testClass->testConvertToDateTime(null, 'DateTime');
        $this->assertNull($result);
    }

    public function test_get_carbon_transformer_from_attributes_without_property(): void
    {
        $result = $this->testClass->testGetCarbonTransformerFromAttributes(null);
        $this->assertNull($result);
    }

    public function test_get_carbon_transformer_from_attributes_with_carbon_date(): void
    {
        $property = new ReflectionProperty(TestClassWithCarbonAttribute::class, 'carbonDate');
        $result = $this->testClass->testGetCarbonTransformerFromAttributes($property);

        $this->assertInstanceOf(CarbonTransformer::class, $result);
    }

    public function test_get_carbon_transformer_from_class_provider(): void
    {
        $property = new ReflectionProperty(TestClassWithCarbonAttribute::class, 'regularDate');
        $provider = new DateTimeProvider('Carbon\\Carbon');

        $result = $this->testClass->testGetCarbonTransformerFromAttributes($property, $provider);

        $this->assertInstanceOf(CarbonTransformer::class, $result);
    }

    public function test_get_class_datetime_provider(): void
    {
        $result = $this->testClass->testGetClassDateTimeProvider(TestClassWithDateTimeProvider::class);

        $this->assertInstanceOf(DateTimeProvider::class, $result);
    }

    public function test_get_class_datetime_provider_without_attribute(): void
    {
        $result = $this->testClass->testGetClassDateTimeProvider(TestClassWithCarbonSupport::class);
        $this->assertNull($result);
    }
}

class TestClassWithCarbonSupport
{
    use HasCarbonSupport;

    public function testConvertToCarbon($value, $typeName, $property = null, $classProvider = null)
    {
        return self::convertToCarbon($value, $typeName, $property, $classProvider);
    }

    public function testConvertToDateTime($value, $typeName, $property = null, $classProvider = null)
    {
        return self::convertToDateTime($value, $typeName, $property, $classProvider);
    }

    public function testGetCarbonTransformerFromAttributes($property = null, $classProvider = null)
    {
        return self::getCarbonTransformerFromAttributes($property, $classProvider);
    }

    public function testGetClassDateTimeProvider($class)
    {
        return self::getClassDateTimeProvider($class);
    }
}

class TestClassWithCarbonAttribute
{
    #[CarbonDate('Y-m-d')]
    public $carbonDate;

    public $regularDate;
}

#[DateTimeProvider('Carbon\\Carbon')]
class TestClassWithDateTimeProvider
{
    public $date;
}

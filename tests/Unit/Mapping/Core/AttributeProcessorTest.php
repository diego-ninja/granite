<?php

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Attributes\MapCollection;
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWhen;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\Core\AttributeProcessor;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionProperty;
use Tests\Helpers\TestCase;

#[CoversClass(AttributeProcessor::class)]
class AttributeProcessorTest extends TestCase
{
    private AttributeProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new AttributeProcessor();
    }

    public function test_process_property_without_attributes(): void
    {
        $property = new ReflectionProperty(TestClass::class, 'name');
        $config = $this->processor->processProperty($property);

        $this->assertEquals([
            'source' => 'name',
            'transformer' => null,
            'condition' => null,
            'default' => null,
            'hasDefault' => false,
            'ignore' => false,
        ], $config);
    }

    public function test_process_property_with_map_from(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'mappedName');
        $config = $this->processor->processProperty($property);

        $this->assertEquals('source_name', $config['source']);
        $this->assertFalse($config['ignore']);
    }

    public function test_process_property_with_ignore(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'ignoredProperty');
        $config = $this->processor->processProperty($property);

        $this->assertTrue($config['ignore']);
    }

    public function test_process_property_with_map_with(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'transformedProperty');
        $config = $this->processor->processProperty($property);

        $this->assertIsCallable($config['transformer']);
    }

    public function test_build_carbon_transformer_without_attributes(): void
    {
        $property = new ReflectionProperty(TestClass::class, 'name');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertNull($transformer);
    }

    public function test_build_carbon_transformer_with_carbon_date(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'carbonDate');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
    }

    public function test_has_carbon_attributes_false(): void
    {
        $property = new ReflectionProperty(TestClass::class, 'name');
        $hasCarbon = $this->processor->hasCarbonAttributes($property);

        $this->assertFalse($hasCarbon);
    }

    public function test_has_carbon_attributes_true(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'carbonDate');
        $hasCarbon = $this->processor->hasCarbonAttributes($property);

        $this->assertTrue($hasCarbon);
    }

    public function test_get_carbon_attributes_empty(): void
    {
        $property = new ReflectionProperty(TestClass::class, 'name');
        $attributes = $this->processor->getCarbonAttributes($property);

        $this->assertEmpty($attributes);
    }

    public function test_get_carbon_attributes_with_carbon_date(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'carbonDate');
        $attributes = $this->processor->getCarbonAttributes($property);

        $this->assertNotEmpty($attributes);
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(CarbonDate::class, $attributes[0]);
    }

    public function test_process_multiple_attributes(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'multipleAttributes');
        $config = $this->processor->processProperty($property);

        $this->assertEquals('multi_source', $config['source']);
        $this->assertIsCallable($config['transformer']);
    }

    public function test_process_property_returns_array(): void
    {
        $property = new ReflectionProperty(TestClass::class, 'name');
        $config = $this->processor->processProperty($property);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('source', $config);
        $this->assertArrayHasKey('transformer', $config);
        $this->assertArrayHasKey('condition', $config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('hasDefault', $config);
        $this->assertArrayHasKey('ignore', $config);
    }

    public function test_carbon_transformer_creation_with_format(): void
    {
        $property = new ReflectionProperty(TestClassWithAttributes::class, 'carbonDate');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
    }

    public function test_process_property_with_map_default(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'propertyWithDefault');
        $config = $this->processor->processProperty($property);

        $this->assertEquals('default_value', $config['default']);
        $this->assertTrue($config['hasDefault']);
    }

    public function test_process_property_with_map_when(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'conditionalProperty');
        $config = $this->processor->processProperty($property);

        $this->assertIsCallable($config['condition']);
    }

    public function test_process_property_with_map_collection(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'collectionProperty');
        $config = $this->processor->processProperty($property);

        $this->assertNotNull($config['transformer']);
    }

    public function test_build_carbon_transformer_with_carbon_range(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'carbonRange');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
    }

    public function test_build_carbon_transformer_with_carbon_relative(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'carbonRelative');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
    }

    public function test_build_carbon_transformer_with_multiple_carbon_attributes(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'multipleCarbonAttributes');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
    }

    public function test_has_carbon_attributes_with_range(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'carbonRange');
        $hasCarbon = $this->processor->hasCarbonAttributes($property);

        $this->assertTrue($hasCarbon);
    }

    public function test_has_carbon_attributes_with_relative(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'carbonRelative');
        $hasCarbon = $this->processor->hasCarbonAttributes($property);

        $this->assertTrue($hasCarbon);
    }

    public function test_get_carbon_attributes_with_multiple_types(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'multipleCarbonAttributes');
        $attributes = $this->processor->getCarbonAttributes($property);

        $this->assertCount(2, $attributes);
    }

    public function test_process_property_with_carbon_range_sets_flag(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'carbonRange');
        $config = $this->processor->processProperty($property);

        $this->assertTrue($config['hasCarbonAttributes'] ?? false);
    }

    public function test_process_property_with_carbon_relative_sets_flag(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'carbonRelative');
        $config = $this->processor->processProperty($property);

        $this->assertTrue($config['hasCarbonAttributes'] ?? false);
    }

    public function test_build_carbon_transformer_returns_null_without_relevant_attributes(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'regularProperty');
        $transformer = $this->processor->buildCarbonTransformer($property);

        $this->assertNull($transformer);
    }

    public function test_get_carbon_attributes_returns_instances_in_order(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'multipleCarbonAttributes');
        $attributes = $this->processor->getCarbonAttributes($property);

        $this->assertCount(2, $attributes);
        $this->assertInstanceOf(CarbonRange::class, $attributes[0]);
        $this->assertInstanceOf(CarbonRelative::class, $attributes[1]);
    }

    public function test_process_property_ignores_unknown_attributes(): void
    {
        $property = new ReflectionProperty(TestClassWithMoreAttributes::class, 'regularProperty');
        $config = $this->processor->processProperty($property);

        // Should process normally without any attributes
        $this->assertEquals('regularProperty', $config['source']);
        $this->assertFalse($config['ignore']);
    }
}

class TestClass
{
    public string $name;
    public int $age;
}

class TestClassWithAttributes
{
    #[MapFrom('source_name')]
    public string $mappedName;

    #[Ignore]
    public string $ignoredProperty;

    #[MapWith([self::class, 'transform'])]
    public string $transformedProperty;

    #[CarbonDate('Y-m-d')]
    public $carbonDate;

    #[MapFrom('multi_source')]
    #[MapWith([self::class, 'transform'])]
    public string $multipleAttributes;

    public static function transform($value): string
    {
        return strtoupper($value);
    }
}

class TestClassWithMoreAttributes
{
    #[MapDefault('default_value')]
    public string $propertyWithDefault;

    #[MapWhen([self::class, 'shouldMap'])]
    public string $conditionalProperty;

    #[MapCollection('string')]
    public array $collectionProperty;

    #[CarbonRange('2020-01-01', '2030-12-31')]
    public $carbonRange;

    #[CarbonRelative(false)]
    public $carbonRelative;

    #[CarbonRange('2020-01-01', '2030-12-31')]
    #[CarbonRelative(false)]
    public $multipleCarbonAttributes;

    public string $regularProperty;

    public static function shouldMap($data): bool
    {
        return isset($data['enabled']);
    }
}

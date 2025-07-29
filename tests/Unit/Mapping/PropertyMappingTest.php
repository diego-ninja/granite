<?php

// tests/Unit/Mapping/PropertyMappingTest.php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Exception;
use Ninja\Granite\Mapping\PropertyMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Fixtures\Automapper\TestTransformer;
use Tests\Helpers\TestCase;

#[CoversClass(PropertyMapping::class)]
class PropertyMappingTest extends TestCase
{
    private PropertyMapping $mapping;

    protected function setUp(): void
    {
        $this->mapping = new PropertyMapping();
        parent::setUp();
    }

    public static function transformerTypesProvider(): array
    {
        return [
            'closure' => [
                fn($value) => 'closure: ' . $value,
                'test',
                'closure: test',
            ],
            'callable array' => [
                [self::class, 'staticTransformer'],
                'test',
                'static: test',
            ],
            'transformer instance' => [
                new TestTransformer(),
                'test',
                'TRANSFORMED: test',
            ],
        ];
    }

    public static function staticTransformer($value): string
    {
        return 'static: ' . $value;
    }

    public function test_creates_property_mapping(): void
    {
        $this->assertInstanceOf(PropertyMapping::class, $this->mapping);
    }

    public function test_map_from_sets_source_property(): void
    {
        $result = $this->mapping->mapFrom('sourceProperty');

        $this->assertSame($this->mapping, $result); // Method chaining
        $this->assertEquals('sourceProperty', $this->mapping->getSourceProperty());
    }

    public function test_using_sets_transformer_with_callable(): void
    {
        $transformer = fn($value) => mb_strtoupper($value);

        $result = $this->mapping->using($transformer);

        $this->assertSame($this->mapping, $result); // Method chaining
    }

    public function test_using_sets_transformer_with_transformer_instance(): void
    {
        $transformer = new TestTransformer();

        $result = $this->mapping->using($transformer);

        $this->assertSame($this->mapping, $result); // Method chaining
    }

    public function test_ignore_marks_property_as_ignored(): void
    {
        $result = $this->mapping->ignore();

        $this->assertSame($this->mapping, $result); // Method chaining
        $this->assertTrue($this->mapping->isIgnored());
    }

    public function test_transforms_value_with_callable(): void
    {
        $transformer = fn($value) => mb_strtoupper($value);
        $this->mapping->using($transformer);

        $result = $this->mapping->transform('hello', []);

        $this->assertEquals('HELLO', $result);
    }

    public function test_transforms_value_with_transformer_instance(): void
    {
        $transformer = new TestTransformer();
        $this->mapping->using($transformer);

        $result = $this->mapping->transform('test', []);

        $this->assertEquals('TRANSFORMED: test', $result);
    }

    public function test_transforms_value_with_source_data_context(): void
    {
        $transformer = fn($value, $sourceData) => $value . ' from ' . ($sourceData['location'] ?? 'unknown');

        $this->mapping->using($transformer);

        $result = $this->mapping->transform('hello', ['location' => 'test']);

        $this->assertEquals('hello from test', $result);
    }

    public function test_returns_null_when_ignored(): void
    {
        $this->mapping->ignore();

        $result = $this->mapping->transform('any value', []);

        $this->assertNull($result);
    }

    public function test_returns_original_value_without_transformer(): void
    {
        $result = $this->mapping->transform('original', []);

        $this->assertEquals('original', $result);
    }

    public function test_chaining_methods(): void
    {
        $result = $this->mapping
            ->mapFrom('sourceField')
            ->using(fn($value) => mb_strtoupper($value));

        $this->assertSame($this->mapping, $result);
        $this->assertEquals('sourceField', $this->mapping->getSourceProperty());
    }

    public function test_source_property_is_null_by_default(): void
    {
        $this->assertNull($this->mapping->getSourceProperty());
    }

    public function test_is_not_ignored_by_default(): void
    {
        $this->assertFalse($this->mapping->isIgnored());
    }

    public function test_overwrites_source_property(): void
    {
        $this->mapping->mapFrom('first');
        $this->mapping->mapFrom('second');

        $this->assertEquals('second', $this->mapping->getSourceProperty());
    }

    public function test_overwrites_transformer(): void
    {
        $transformer1 = fn($value) => 'first: ' . $value;
        $transformer2 = fn($value) => 'second: ' . $value;

        $this->mapping->using($transformer1);
        $this->mapping->using($transformer2);

        $result = $this->mapping->transform('test', []);

        $this->assertEquals('second: test', $result);
    }

    public function test_ignore_overrides_transformer(): void
    {
        $this->mapping->using(fn($value) => 'transformed: ' . $value);
        $this->mapping->ignore();

        $result = $this->mapping->transform('test', []);

        $this->assertNull($result);
    }

    #[DataProvider('transformerTypesProvider')]
    public function test_handles_different_transformer_types(mixed $transformer, string $input, string $expected): void
    {
        $this->mapping->using($transformer);

        $result = $this->mapping->transform($input, []);

        $this->assertEquals($expected, $result);
    }

    public function test_handles_complex_transformations(): void
    {
        $transformer = function ($value, $sourceData) {
            $multiplier = $sourceData['multiplier'] ?? 1;
            $prefix = $sourceData['prefix'] ?? '';

            return $prefix . (is_numeric($value) ? $value * $multiplier : $value);
        };

        $this->mapping->using($transformer);

        // Test with numeric value
        $result1 = $this->mapping->transform(10, ['multiplier' => 2, 'prefix' => 'num: ']);
        $this->assertEquals('num: 20', $result1);

        // Test with string value
        $result2 = $this->mapping->transform('hello', ['prefix' => 'str: ']);
        $this->assertEquals('str: hello', $result2);
    }

    public function test_handles_null_values(): void
    {
        $transformer = fn($value) => null === $value ? 'NULL' : 'NOT_NULL';
        $this->mapping->using($transformer);

        $result1 = $this->mapping->transform(null, []);
        $result2 = $this->mapping->transform('value', []);

        $this->assertEquals('NULL', $result1);
        $this->assertEquals('NOT_NULL', $result2);
    }

    public function test_handles_empty_source_data(): void
    {
        $transformer = fn($value, $sourceData) => $value . ' (count: ' . count($sourceData) . ')';

        $this->mapping->using($transformer);

        $result = $this->mapping->transform('test', []);

        $this->assertEquals('test (count: 0)', $result);
    }

    public function test_performance_with_complex_transformer(): void
    {
        $transformer = function ($value, $sourceData) {
            // Simulate complex transformation
            $result = $value;
            for ($i = 0; $i < 100; $i++) {
                $result = md5($result);
            }
            return mb_substr($result, 0, 8);
        };

        $this->mapping->using($transformer);

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $this->mapping->transform("test{$i}", []);
        }

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.5, $elapsed, "Property transformation took too long: {$elapsed}s");
    }

    public function test_transformer_exception_handling(): void
    {
        $transformer = function ($value) {
            if ('error' === $value) {
                throw new Exception('TestTransformer error');
            }
            return $value;
        };

        $this->mapping->using($transformer);

        // Should handle normal values
        $this->assertEquals('normal', $this->mapping->transform('normal', []));

        // Should propagate exceptions
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('TestTransformer error');
        $this->mapping->transform('error', []);
    }

    public function test_nested_source_property_paths(): void
    {
        $this->mapping->mapFrom('user.profile.name');

        $this->assertEquals('user.profile.name', $this->mapping->getSourceProperty());
    }

    public function test_special_characters_in_source_property(): void
    {
        $specialNames = [
            'property-with-dashes',
            'property_with_underscores',
            'property.with.dots',
            'property[with][brackets]',
            'property with spaces',
        ];

        foreach ($specialNames as $name) {
            $mapping = new PropertyMapping();
            $mapping->mapFrom($name);

            $this->assertEquals($name, $mapping->getSourceProperty());
        }
    }

    public function test_immutability_after_configuration(): void
    {
        $this->mapping->mapFrom('source');
        $this->mapping->using(fn($v) => $v);

        // Configuration should be persistent
        $this->assertEquals('source', $this->mapping->getSourceProperty());

        // Should be able to transform multiple times
        $this->assertEquals('test1', $this->mapping->transform('test1', []));
        $this->assertEquals('test2', $this->mapping->transform('test2', []));
    }

    public function test_works_with_different_value_types(): void
    {
        $transformer = function ($value) {
            return match (gettype($value)) {
                'string' => 'STRING: ' . $value,
                'integer' => 'INT: ' . $value,
                'array' => 'ARRAY: ' . count($value),
                'boolean' => 'BOOL: ' . ($value ? 'true' : 'false'),
                'NULL' => 'NULL',
                default => 'UNKNOWN: ' . gettype($value),
            };
        };

        $this->mapping->using($transformer);

        $testCases = [
            ['hello', 'STRING: hello'],
            [42, 'INT: 42'],
            [[1, 2, 3], 'ARRAY: 3'],
            [true, 'BOOL: true'],
            [false, 'BOOL: false'],
            [null, 'NULL'],
            [new stdClass(), 'UNKNOWN: object'],
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->mapping->transform($input, []);
            $this->assertEquals($expected, $result);
        }
    }
}

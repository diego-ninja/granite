<?php

namespace Tests\Unit\Transformers;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Transformers\CollectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use stdClass;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Helpers\TestCase;

#[CoversClass(CollectionTransformer::class)]
class CollectionTransformerTest extends TestCase
{
    private CollectionTransformer $transformer;
    private Mapper $mockMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockMapper = $this->createMock(Mapper::class);
        $this->mockMapper->method('map')->willReturnCallback(function ($source) {
            if (is_array($source)) {
                return (object) ['name' => 'MAPPED: ' . ($source['name'] ?? 'Unknown')];
            }
            if (is_object($source) && property_exists($source, 'name')) {
                return (object) ['name' => 'MAPPED: ' . $source->name];
            }
            return (object) ['name' => 'MAPPED: Unknown'];
        });

        $this->transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
        );
    }

    public function test_implements_transformer_interface(): void
    {
        $this->assertInstanceOf(Transformer::class, $this->transformer);
    }

    public function test_constructor_creates_instance(): void
    {
        $transformer = new CollectionTransformer('TestClass');
        $this->assertInstanceOf(CollectionTransformer::class, $transformer);
    }

    public function test_set_mapper_returns_self(): void
    {
        $transformer = new CollectionTransformer('TestClass');
        $result = $transformer->setMapper($this->mockMapper);

        $this->assertSame($transformer, $result);
    }

    public function test_transform_null_returns_null(): void
    {
        $result = $this->transformer->transform(null);
        $this->assertNull($result);
    }

    public function test_transform_non_array_returns_as_is(): void
    {
        $result = $this->transformer->transform('not an array');
        $this->assertEquals('not an array', $result);
    }

    public function test_transform_empty_array(): void
    {
        $result = $this->transformer->transform([]);
        $this->assertEquals([], $result);
    }

    public function test_transform_simple_array(): void
    {
        $input = [
            ['name' => 'John', 'id' => 1],
            ['name' => 'Jane', 'id' => 2],
        ];

        $result = $this->transformer->transform($input);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('MAPPED: John', $result[0]->name);
        $this->assertEquals('MAPPED: Jane', $result[1]->name);
    }

    public function test_transform_with_preserve_keys(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            preserveKeys: true,
        );

        $input = [
            'first' => ['name' => 'John', 'id' => 1],
            'second' => ['name' => 'Jane', 'id' => 2],
        ];

        $result = $transformer->transform($input);

        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('second', $result);
        $this->assertEquals('MAPPED: John', $result['first']->name);
        $this->assertEquals('MAPPED: Jane', $result['second']->name);
    }

    public function test_transform_with_item_transformer_callable(): void
    {
        $itemTransformer = fn($item) => 'TRANSFORMED: ' . $item['name'];

        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            itemTransformer: $itemTransformer,
        );

        $input = [
            ['name' => 'John', 'id' => 1],
            ['name' => 'Jane', 'id' => 2],
        ];

        $result = $transformer->transform($input);

        $this->assertEquals('TRANSFORMED: John', $result[0]);
        $this->assertEquals('TRANSFORMED: Jane', $result[1]);
    }

    public function test_transform_with_item_transformer_instance(): void
    {
        $itemTransformer = new MockTransformer();

        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            itemTransformer: $itemTransformer,
        );

        $input = [
            ['name' => 'John', 'id' => 1],
            ['name' => 'Jane', 'id' => 2],
        ];

        $result = $transformer->transform($input);

        $this->assertEquals('MOCK_TRANSFORMED: John', $result[0]);
        $this->assertEquals('MOCK_TRANSFORMED: Jane', $result[1]);
    }

    public function test_transform_recursive_nested_arrays(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            recursive: true,
        );

        $input = [
            [
                ['name' => 'John', 'id' => 1],
                ['name' => 'Jane', 'id' => 2],
            ],
        ];

        $result = $transformer->transform($input);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]);
        $this->assertEquals('MAPPED: John', $result[0][0]->name);
        $this->assertEquals('MAPPED: Jane', $result[0][1]->name);
    }

    public function test_transform_recursive_with_preserve_keys(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            recursive: true,
            preserveKeys: true,
        );

        // This test case has a collection where each inner array is treated as an object
        // because ['user1' => ['name' => 'John', 'id' => 1]] is associative
        $input = [
            'group1' => [
                ['name' => 'John', 'id' => 1],  // Sequential array, treated as collection
                ['name' => 'Jane', 'id' => 2],
            ],
        ];

        $result = $transformer->transform($input);

        $this->assertArrayHasKey('group1', $result);
        $this->assertIsArray($result['group1']);
        $this->assertCount(2, $result['group1']);
        $this->assertEquals('MAPPED: John', $result['group1'][0]->name);
        $this->assertEquals('MAPPED: Jane', $result['group1'][1]->name);
    }

    public function test_transform_recursive_associative_arrays_mapped_as_objects(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            recursive: true,
            preserveKeys: true,
        );

        // This tests that associative arrays are treated as objects to be mapped
        $input = [
            'group1' => ['name' => 'John', 'id' => 1],  // This is associative, should be mapped to object
        ];

        $result = $transformer->transform($input);

        $this->assertArrayHasKey('group1', $result);
        $this->assertInstanceOf(stdClass::class, $result['group1']);
        $this->assertEquals('MAPPED: John', $result['group1']->name);
    }

    public function test_transform_object_items(): void
    {
        $obj1 = new SimpleDTO(1, 'John', 'john@test.com');
        $obj2 = new SimpleDTO(2, 'Jane', 'jane@test.com');

        $input = [$obj1, $obj2];

        $result = $this->transformer->transform($input);

        $this->assertEquals('MAPPED: John', $result[0]->name);
        $this->assertEquals('MAPPED: Jane', $result[1]->name);
    }

    public function test_transform_scalar_items(): void
    {
        $input = ['hello', 'world', 123];

        $result = $this->transformer->transform($input);

        $this->assertEquals(['hello', 'world', 123], $result);
    }

    public function test_transform_without_mapper_throws_exception(): void
    {
        $transformer = new CollectionTransformer(SimpleDTO::class);

        $input = [['name' => 'John', 'id' => 1]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapper is required for object mapping');

        $transformer->transform($input);
    }

    public function test_transform_recursive_without_mapper_throws_exception(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            recursive: true,
        );

        $input = [
            [['name' => 'John', 'id' => 1]],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapper is required for object mapping');

        $transformer->transform($input);
    }

    public function test_is_associative_array_with_sequential_keys(): void
    {
        $input = [
            ['name' => 'John', 'id' => 1],
            ['name' => 'Jane', 'id' => 2],
            ['name' => 'Bob', 'id' => 3],
        ];

        $result = $this->transformer->transform($input);

        $this->assertCount(3, $result);
        $this->assertEquals('MAPPED: John', $result[0]->name);
        $this->assertEquals('MAPPED: Jane', $result[1]->name);
        $this->assertEquals('MAPPED: Bob', $result[2]->name);
    }

    public function test_is_associative_array_with_non_sequential_keys(): void
    {
        $input = [
            1 => ['name' => 'John', 'id' => 1],
            3 => ['name' => 'Jane', 'id' => 2],
        ];

        $result = $this->transformer->transform($input);

        $this->assertCount(2, $result);
        $this->assertEquals('MAPPED: John', $result[0]->name);
        $this->assertEquals('MAPPED: Jane', $result[1]->name);
    }

    public function test_transform_with_source_data(): void
    {
        $input = [['name' => 'John', 'id' => 1]];
        $sourceData = ['context' => 'test'];

        $result = $this->transformer->transform($input, $sourceData);

        $this->assertEquals('MAPPED: John', $result[0]->name);
    }

    public function test_all_constructor_options(): void
    {
        $itemTransformer = fn($item) => 'CUSTOM: ' . $item['name'];

        $transformer = new CollectionTransformer(
            destinationType: 'CustomClass',
            mapper: $this->mockMapper,
            preserveKeys: true,
            recursive: true,
            itemTransformer: $itemTransformer,
        );

        $this->assertInstanceOf(CollectionTransformer::class, $transformer);
    }

    public function test_transform_recursive_associative_array(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            recursive: true,
        );

        // This tests the isAssociativeArray path
        $input = [
            ['name' => 'John', 'id' => 1], // This should be treated as associative
        ];

        $result = $transformer->transform($input);

        $this->assertCount(1, $result);
        $this->assertEquals('MAPPED: John', $result[0]->name);
    }

    public function test_transform_nested_empty_array(): void
    {
        $transformer = new CollectionTransformer(
            destinationType: SimpleDTO::class,
            mapper: $this->mockMapper,
            recursive: true,
        );

        $input = [[]]; // Empty nested array

        $result = $transformer->transform($input);

        $this->assertCount(1, $result);
        $this->assertEquals('MAPPED: Unknown', $result[0]->name);
    }

    public function test_constructor_with_all_parameters(): void
    {
        $mapper = $this->mockMapper;
        $itemTransformer = fn($x) => $x;

        $transformer = new CollectionTransformer(
            destinationType: 'TestType',
            mapper: $mapper,
            preserveKeys: true,
            recursive: true,
            itemTransformer: $itemTransformer,
        );

        $this->assertInstanceOf(CollectionTransformer::class, $transformer);
    }
}

class MockTransformer implements Transformer
{
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if (is_array($value) && isset($value['name'])) {
            return 'MOCK_TRANSFORMED: ' . $value['name'];
        }
        return 'MOCK_TRANSFORMED: ' . (string) $value;
    }
}

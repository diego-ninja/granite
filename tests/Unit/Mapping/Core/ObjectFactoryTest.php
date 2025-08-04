<?php

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Mapping\Core\ObjectFactory;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(ObjectFactory::class)]
class ObjectFactoryTest extends TestCase
{
    private ObjectFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ObjectFactory();
    }

    public function test_create_stdclass(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = $this->factory->create($data, stdClass::class);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertEquals('John', $result->name);
        $this->assertEquals(30, $result->age);
    }

    public function test_create_granite_object(): void
    {
        $data = ['name' => 'Jane'];
        $result = $this->factory->create($data, TestGraniteVO::class);

        $this->assertInstanceOf(TestGraniteVO::class, $result);
        $this->assertEquals('Jane', $result->name);
    }

    public function test_create_simple_class_with_constructor(): void
    {
        $data = ['name' => 'Bob', 'age' => 25];
        $result = $this->factory->create($data, TestSimpleClass::class);

        $this->assertInstanceOf(TestSimpleClass::class, $result);
        $this->assertEquals('Bob', $result->name);
        $this->assertEquals(25, $result->age);
    }

    public function test_create_class_without_constructor(): void
    {
        $data = ['title' => 'Test'];
        $result = $this->factory->create($data, TestClassWithoutConstructor::class);

        $this->assertInstanceOf(TestClassWithoutConstructor::class, $result);
        $this->assertEquals('Test', $result->title);
    }

    public function test_create_with_optional_parameters(): void
    {
        $data = ['name' => 'Alice'];
        $result = $this->factory->create($data, TestClassWithOptionalParams::class);

        $this->assertInstanceOf(TestClassWithOptionalParams::class, $result);
        $this->assertEquals('Alice', $result->name);
        $this->assertEquals('default', $result->role);
    }

    public function test_create_with_nullable_parameters(): void
    {
        $data = ['name' => 'Charlie'];
        $result = $this->factory->create($data, TestClassWithNullableParam::class);

        $this->assertInstanceOf(TestClassWithNullableParam::class, $result);
        $this->assertEquals('Charlie', $result->name);
        $this->assertNull($result->email);
    }

    public function test_create_with_typed_parameters(): void
    {
        $data = ['name' => 'David'];
        $result = $this->factory->create($data, TestClassWithTypedParams::class);

        $this->assertInstanceOf(TestClassWithTypedParams::class, $result);
        $this->assertEquals('David', $result->name);
        $this->assertEquals(0, $result->age);
        $this->assertEquals(0.0, $result->score);
        $this->assertFalse($result->active);
        $this->assertEquals('', $result->description);
        $this->assertEquals([], $result->tags);
    }

    public function test_populate_existing_object(): void
    {
        $object = new TestClassWithoutConstructor();
        $data = ['title' => 'Updated Title'];

        $result = $this->factory->populate($object, $data);

        $this->assertSame($object, $result);
        $this->assertEquals('Updated Title', $result->title);
    }

    public function test_populate_ignores_readonly_properties(): void
    {
        $object = new TestClassWithReadonlyProperty('original');
        $data = ['value' => 'updated'];

        $result = $this->factory->populate($object, $data);

        $this->assertSame($object, $result);
        $this->assertEquals('original', $result->value);
    }

    public function test_create_throws_exception_on_invalid_class(): void
    {
        $this->expectException(MappingException::class);
        $this->factory->create([], 'NonExistentClass');
    }

    public function test_populate_ignores_private_properties(): void
    {
        $object = new TestClassWithPrivateProperty();
        $data = ['private' => 'should not be set', 'public' => 'should be set'];

        $result = $this->factory->populate($object, $data);

        $this->assertSame($object, $result);
        $this->assertEquals('should be set', $result->public);
        $this->assertEquals('default', $result->getPrivate()); // unchanged
    }

    public function test_populate_ignores_non_existent_properties(): void
    {
        $object = new TestClassWithoutConstructor();
        $data = ['title' => 'Valid', 'nonexistent' => 'Invalid'];

        $result = $this->factory->populate($object, $data);

        $this->assertSame($object, $result);
        $this->assertEquals('Valid', $result->title);
    }

    public function test_populate_handles_exceptions_gracefully(): void
    {
        $object = new TestClassWithThrowingProperty();
        $data = ['title' => 'Valid'];

        // Should not throw exception and should complete successfully
        $result = $this->factory->populate($object, $data);

        $this->assertSame($object, $result);
    }

    public function test_create_with_constructor_and_extra_properties(): void
    {
        $data = ['name' => 'Extra', 'age' => 30, 'extra' => 'value'];
        $result = $this->factory->create($data, TestClassWithExtraProperty::class);

        $this->assertInstanceOf(TestClassWithExtraProperty::class, $result);
        $this->assertEquals('Extra', $result->name);
        $this->assertEquals(30, $result->age);
        $this->assertEquals('value', $result->extra);
    }

    public function test_create_with_union_type_parameter(): void
    {
        $data = ['name' => 'Union', 'value' => 'test'];
        $result = $this->factory->create($data, TestClassWithUnionType::class);

        $this->assertInstanceOf(TestClassWithUnionType::class, $result);
        $this->assertEquals('Union', $result->name);
        $this->assertEquals('test', $result->value); // Union types get the actual value
    }

    public function test_create_handles_reflection_exceptions(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Failed to create instance');

        // Try to create a class that doesn't exist
        $this->factory->create(['test' => 'data'], 'NonExistentClass');
    }

    public function test_populate_throws_exception_on_error(): void
    {
        $object = new TestClassWithBadProperty();
        $data = ['title' => 'test'];

        // This should work fine since we handle exceptions gracefully
        $result = $this->factory->populate($object, $data);
        $this->assertSame($object, $result);
    }

    public function test_get_default_value_for_all_types(): void
    {
        // Test by creating objects with various typed parameters that have no data
        $data = ['name' => 'TypeTest'];
        $result = $this->factory->create($data, TestClassWithAllTypes::class);

        $this->assertInstanceOf(TestClassWithAllTypes::class, $result);
        $this->assertEquals('TypeTest', $result->name);
        $this->assertEquals(0, $result->intValue);
        $this->assertEquals(0.0, $result->floatValue);
        $this->assertFalse($result->boolValue);
        $this->assertEquals('', $result->stringValue);
        $this->assertEquals([], $result->arrayValue);
        $this->assertNull($result->objectValue);
    }
}

readonly class TestGraniteVO extends GraniteVO
{
    public string $name;
}

class TestSimpleClass
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

class TestClassWithoutConstructor
{
    public string $title;
}

class TestClassWithOptionalParams
{
    public function __construct(
        public string $name,
        public string $role = 'default',
    ) {}
}

class TestClassWithNullableParam
{
    public function __construct(
        public string $name,
        public ?string $email = null,
    ) {}
}

class TestClassWithTypedParams
{
    public function __construct(
        public string $name,
        public int $age,
        public float $score,
        public bool $active,
        public string $description,
        public array $tags,
    ) {}
}

class TestClassWithReadonlyProperty
{
    public function __construct(
        public readonly string $value,
    ) {}
}

class TestClassWithPrivateProperty
{
    public string $public = '';
    private string $private = 'default';

    public function getPrivate(): string
    {
        return $this->private;
    }
}

class TestClassWithThrowingProperty
{
    public string $title;
}

class TestClassWithExtraProperty
{
    public string $extra;

    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

class TestClassWithUnionType
{
    public function __construct(
        public string $name,
        public string|int|null $value = null,
    ) {}
}

abstract class TestAbstractClass
{
    public function __construct(public string $test) {}
}

class TestClassWithBadProperty
{
    public string $title;
}

class TestClassWithAllTypes
{
    public function __construct(
        public string $name,
        public int $intValue,
        public float $floatValue,
        public bool $boolValue,
        public string $stringValue,
        public array $arrayValue,
        public ?object $objectValue,
    ) {}
}

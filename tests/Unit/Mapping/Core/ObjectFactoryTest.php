<?php

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Mapping\Core\ObjectFactory;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;
use TypeError;

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

    public function test_create_with_missing_required_typed_parameter_fails(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Missing required value');

        $this->factory->create(['name' => 'David'], TestClassWithTypedParams::class);
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

    public function test_populate_reports_property_type_error(): void
    {
        $object = new TestClassWithThrowingProperty();
        $data = ['title' => ['invalid']];

        try {
            $this->factory->populate($object, $data);
            $this->fail('Expected a MappingException');
        } catch (MappingException $exception) {
            $this->assertSame('title', $exception->getPropertyName());
            $this->assertInstanceOf(TypeError::class, $exception->getPrevious());
            $this->assertStringContainsString('Failed to populate property "title"', $exception->getMessage());
        }
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

    public function test_populate_throws_exception_on_type_error(): void
    {
        $object = new TestClassWithBadProperty();
        $data = ['title' => 'test'];

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('title');

        $this->factory->populate($object, $data);
    }

    public function test_create_does_not_invent_defaults_for_required_parameters(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Missing required value');

        $this->factory->create(['name' => 'TypeTest'], TestClassWithAllTypes::class);
    }

    public function test_create_preserves_constructor_type_error(): void
    {
        try {
            $this->factory->create(['age' => 'not-an-int'], TestClassWithStrictType::class);
            $this->fail('Expected a MappingException');
        } catch (MappingException $exception) {
            $this->assertInstanceOf(TypeError::class, $exception->getPrevious());
            $this->assertStringContainsString('Failed to create instance', $exception->getMessage());
        }
    }

    public function test_create_rejects_abstract_class(): void
    {
        $this->expectException(MappingException::class);

        $this->factory->create(['test' => 'value'], TestAbstractClass::class);
    }

    public function test_create_rejects_private_constructor(): void
    {
        $this->expectException(MappingException::class);

        $this->factory->create([], TestClassWithPrivateConstructor::class);
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
    public int $title;
}

class TestClassWithStrictType
{
    public function __construct(public int $age) {}
}

class TestClassWithPrivateConstructor
{
    private function __construct() {}
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

<?php

namespace Tests\Unit\Traits;

use InvalidArgumentException;
use Ninja\Granite\GraniteVO;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Traits\HasDeserialization;
use ReflectionProperty;
use ReflectionType;
use Tests\Helpers\TestCase;
use Tests\Unit\Support\HydrationTestClass;

class HasDeserializationTest extends TestCase
{
    public function test_from_with_array(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = TestDeserializationClass::from($data);

        $this->assertInstanceOf(TestDeserializationClass::class, $result);
        $this->assertEquals('John', $result->name);
        $this->assertEquals(30, $result->age);
    }

    public function test_from_with_json_string(): void
    {
        $json = '{"name": "Jane", "age": 25}';
        $result = TestDeserializationClass::from($json);

        $this->assertInstanceOf(TestDeserializationClass::class, $result);
        $this->assertEquals('Jane', $result->name);
        $this->assertEquals(25, $result->age);
    }

    public function test_from_with_granite_object(): void
    {
        $source = TestDeserializationClass::from(['name' => 'Bob', 'age' => 35]);
        $result = TestDeserializationClass::from($source);

        $this->assertInstanceOf(TestDeserializationClass::class, $result);
        $this->assertEquals('Bob', $result->name);
        $this->assertEquals(35, $result->age);
    }

    public function test_from_with_scalar_value(): void
    {
        $result = TestScalarDeserializationClass::from('John');

        $this->assertInstanceOf(TestScalarDeserializationClass::class, $result);
        $this->assertEquals('John', $result->name);
    }

    public function test_from_with_positional_arguments(): void
    {
        $result = TestPositionalArgsClass::from('Alice', 28);

        $this->assertInstanceOf(TestPositionalArgsClass::class, $result);
        $this->assertEquals('Alice', $result->name);
        $this->assertEquals(28, $result->age);
    }

    public function test_from_with_named_parameters(): void
    {
        $result = TestNamedParametersClass::from(name: 'Bob', age: 40);

        $this->assertInstanceOf(TestNamedParametersClass::class, $result);
        $this->assertEquals('Bob', $result->name);
        $this->assertEquals(40, $result->age);
    }

    public function test_resolve_arguments_to_data(): void
    {
        $result = TestDeserializationClass::testResolveArgumentsToData([['name' => 'John', 'age' => 30]]);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function test_map_scalar_to_first_property(): void
    {
        $result = TestScalarDeserializationClass::testMapScalarToFirstProperty('John');

        $this->assertEquals(['name' => 'John'], $result);
    }

    public function test_looks_like_structured_data(): void
    {
        $this->assertTrue(TestDeserializationClass::testLooksLikeStructuredData(['key' => 'value']));
        $this->assertTrue(TestDeserializationClass::testLooksLikeStructuredData('{"key": "value"}'));
        $this->assertFalse(TestDeserializationClass::testLooksLikeStructuredData('simple string'));
        $this->assertFalse(TestDeserializationClass::testLooksLikeStructuredData(123));
    }

    public function test_build_from_positional_args(): void
    {
        $result = TestPositionalArgsClass::testBuildFromPositionalArgs(['Alice', 28]);

        $this->assertEquals(['name' => 'Alice', 'age' => 28], $result);
    }

    public function test_normalize_input_data_array(): void
    {
        $result = TestDeserializationClass::testNormalizeInputData(['name' => 'John']);

        $this->assertEquals(['name' => 'John'], $result);
    }

    public function test_normalize_input_data_json(): void
    {
        $result = TestDeserializationClass::testNormalizeInputData('{"name": "John"}');

        $this->assertEquals(['name' => 'John'], $result);
    }

    public function test_normalize_input_data_granite_object(): void
    {
        $source = TestDeserializationClass::from(['name' => 'John']);
        $result = TestDeserializationClass::testNormalizeInputData($source);

        $this->assertEquals(['name' => 'John'], $result);
    }

    public function test_create_empty_instance(): void
    {
        $result = TestDeserializationClass::testCreateEmptyInstance();

        $this->assertInstanceOf(TestDeserializationClass::class, $result);
    }

    public function test_has_readonly_properties_from_parent_classes(): void
    {
        $result = TestReadonlyClass::testHasReadonlyPropertiesFromParentClasses();

        $this->assertIsBool($result);
    }

    public function test_from_with_empty_args(): void
    {
        $result = TestDeserializationClass::from();

        $this->assertInstanceOf(TestDeserializationClass::class, $result);
        // This just tests that an empty instance can be created
        // We can't access properties that might not be initialized
    }

    public function test_from_with_mixed_positional_and_named_args(): void
    {
        $result = TestNamedParametersClass::from(['name' => 'BaseData'], age: 42);

        $this->assertInstanceOf(TestNamedParametersClass::class, $result);
        $this->assertEquals('BaseData', $result->name);
        $this->assertEquals(42, $result->age);
    }

    public function test_from_with_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string provided');

        TestDeserializationClass::from('{invalid json}');
    }

    public function test_normalize_input_data_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string provided');

        TestDeserializationClass::testNormalizeInputData('invalid json');
    }

    public function test_from_named_parameters_method(): void
    {
        $result = TestNamedParametersClass::testFromNamedParameters(['name' => 'Test', 'age' => 25]);

        $this->assertInstanceOf(TestNamedParametersClass::class, $result);
        $this->assertEquals('Test', $result->name);
        $this->assertEquals(25, $result->age);
    }

    public function test_from_named_parameters_with_data_param(): void
    {
        $namedParams = [
            'data' => ['name' => 'FromData', 'age' => 30],
            'age' => 35,  // Should override data
        ];

        $result = TestNamedParametersClass::testFromNamedParameters($namedParams);

        $this->assertInstanceOf(TestNamedParametersClass::class, $result);
        $this->assertEquals('FromData', $result->name);
        $this->assertEquals(35, $result->age); // Named param should override
    }

    public function test_from_named_parameters_filtering_nulls(): void
    {
        $namedParams = [
            'name' => 'Test',
            'age' => null,  // Should be filtered out
            'data' => null,  // Should be removed
        ];

        $result = TestNamedParametersClass::testFromNamedParameters($namedParams);

        $this->assertInstanceOf(TestNamedParametersClass::class, $result);
        $this->assertEquals('Test', $result->name);
        // We can't access age property if it's not initialized, just check the instance was created
    }

    public function test_hydrate_instance(): void
    {
        $instance = TestDeserializationClass::testCreateEmptyInstance();
        $data = ['name' => 'Hydrated', 'age' => 99];

        $result = TestDeserializationClass::testHydrateInstance($instance, $data);

        $this->assertInstanceOf(TestDeserializationClass::class, $result);
        $this->assertEquals('Hydrated', $result->name);
        $this->assertEquals(99, $result->age);
    }
    public function test_case_non_nullable_with_default_null_applied(): void
    {
        $data = ['defaultNonNullable' => null];
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('defaultNonNullable', $result->defaultNonNullable);
    }

    public function test_case_non_nullable_with_default_undefined_applied(): void
    {
        $data = []; // undefined
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('defaultNonNullable', $result->defaultNonNullable);
    }

    public function test_case_nullable_with_default_undefined_applied(): void
    {
        $data = []; // undefined
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('defaultNullable', $result->defaultNullable);
    }

    public function test_case_nullable_without_default_null_respected(): void
    {
        $data = ['noDefaultNullable' => null];
        $result = HydrationTestClass::testHydrate($data);

        $this->assertNull($result->noDefaultNullable);
    }

    public function test_case_nullable_without_default_undefined_respected(): void
    {
        $data = []; // undefined
        $result = HydrationTestClass::testHydrate($data);

        $reflection = new ReflectionProperty(HydrationTestClass::class, 'noDefaultNullable');
        $this->assertFalse(
            $reflection->isInitialized($result),
            'Expected noDefaultNullable to remain uninitialized',
        );
    }

    public function test_case_non_nullable_without_default_value_applied(): void
    {
        $data = ['noDefaultNonNullable' => 'value'];
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('value', $result->noDefaultNonNullable);
    }

    public function test_case_non_nullable_with_default_value_applied(): void
    {
        $data = ['defaultNonNullable' => 'custom'];
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('custom', $result->defaultNonNullable);
    }

    public function test_case_nullable_without_default_value_applied(): void
    {
        $data = ['noDefaultNullable' => 'something'];
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('something', $result->noDefaultNullable);
    }

    public function test_case_nullable_with_default_value_applied(): void
    {
        $data = ['defaultNullable' => 'customNullable'];
        $result = HydrationTestClass::testHydrate($data);

        $this->assertEquals('customNullable', $result->defaultNullable);
    }

    public function test_create_instance_with_constructor(): void
    {
        $data = ['name' => 'Constructor', 'age' => 77];

        $result = TestConstructorClass::testCreateInstanceWithConstructor($data);

        $this->assertInstanceOf(TestConstructorClass::class, $result);
        $this->assertEquals('Constructor', $result->name);
        $this->assertEquals(77, $result->age);
    }

    public function test_create_instance_with_constructor_no_constructor(): void
    {
        $data = ['name' => 'NoConstructor', 'age' => 88];

        $result = TestEmptyClass::testCreateInstanceWithConstructor($data);

        $this->assertInstanceOf(TestEmptyClass::class, $result);
    }

    public function test_hydrate_remaining_properties(): void
    {
        $instance = new TestConstructorClass('Initial', 10);
        $data = ['name' => 'Updated', 'age' => 50, 'extra' => 'value'];

        $result = TestConstructorClass::testHydrateRemainingProperties($instance, $data);

        $this->assertInstanceOf(TestConstructorClass::class, $result);
        // Constructor properties shouldn't be overridden
        $this->assertEquals('Initial', $result->name);
        $this->assertEquals(10, $result->age);
    }

    public function test_resolve_arguments_multiple_scalar(): void
    {
        $args = ['John', 30, 'extra'];
        $result = TestDeserializationClass::testResolveArgumentsToData($args);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function test_resolve_arguments_mixed_structured_and_scalar(): void
    {
        $args = [['name' => 'Base'], 40]; // Array + scalar
        $result = TestDeserializationClass::testResolveArgumentsToData($args);

        // The actual implementation behavior: when mixing array and scalar,
        // the scalar overwrites based on position
        $this->assertEquals(['name' => 40], $result);
    }

    public function test_looks_like_structured_data_edge_cases(): void
    {
        // Test array-like string that looks like JSON (method returns true for [ prefix)
        $this->assertTrue(TestDeserializationClass::testLooksLikeStructuredData('[not json'));

        // Test object-like string that looks like JSON (method returns true for { prefix)
        $this->assertTrue(TestDeserializationClass::testLooksLikeStructuredData('{not json'));

        // Test actual JSON array
        $this->assertTrue(TestDeserializationClass::testLooksLikeStructuredData('["valid", "json"]'));

        // Test null and boolean
        $this->assertFalse(TestDeserializationClass::testLooksLikeStructuredData(null));
        $this->assertFalse(TestDeserializationClass::testLooksLikeStructuredData(true));
    }

    public function test_map_scalar_to_first_property_no_properties(): void
    {
        $result = TestEmptyClass::testMapScalarToFirstProperty('value');

        $this->assertEquals([], $result);
    }
}

readonly class TestDeserializationClass extends GraniteVO
{
    use HasDeserialization;

    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}

    // Expose protected methods for testing
    public static function testResolveArgumentsToData(array $args): array
    {
        return self::resolveArgumentsToData($args);
    }

    public static function testMapScalarToFirstProperty(mixed $value): array
    {
        return self::mapScalarToFirstProperty($value);
    }

    public static function testLooksLikeStructuredData(mixed $value): bool
    {
        return self::looksLikeStructuredData($value);
    }

    public static function testBuildFromPositionalArgs(array $args): array
    {
        return self::buildFromPositionalArgs($args);
    }

    public static function testNormalizeInputData(mixed $data): array
    {
        return self::normalizeInputData($data);
    }

    public static function testCreateEmptyInstance(): object
    {
        return self::createEmptyInstance();
    }

    public static function testHasReadonlyPropertiesFromParentClasses(): bool
    {
        return self::hasReadonlyPropertiesFromParentClasses();
    }

    public static function testHydrateInstance(object $instance, array $data): static
    {
        return self::hydrateInstance($instance, $data);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

readonly class TestScalarDeserializationClass extends GraniteVO
{
    use HasDeserialization;

    public function __construct(
        public string $name = '',
    ) {}

    public static function testMapScalarToFirstProperty(mixed $value): array
    {
        return self::mapScalarToFirstProperty($value);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

readonly class TestPositionalArgsClass extends GraniteVO
{
    use HasDeserialization;

    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}

    public static function testBuildFromPositionalArgs(array $args): array
    {
        return self::buildFromPositionalArgs($args);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

readonly class TestNamedParametersClass extends GraniteVO
{
    use HasDeserialization;

    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}

    public static function testFromNamedParameters(array $namedParams): static
    {
        return self::fromNamedParameters($namedParams);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

readonly class TestReadonlyClass extends GraniteVO
{
    use HasDeserialization;

    public function __construct(
        public readonly string $name = '',
    ) {}

    public static function testHasReadonlyPropertiesFromParentClasses(): bool
    {
        return self::hasReadonlyPropertiesFromParentClasses();
    }

    public static function testFromNamedParameters(array $namedParams): static
    {
        return self::fromNamedParameters($namedParams);
    }

    public static function testHydrateInstance(object $instance, array $data): static
    {
        return self::hydrateInstance($instance, $data);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

readonly class TestConstructorClass extends GraniteVO
{
    use HasDeserialization;

    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}

    public static function testCreateInstanceWithConstructor(array $data): static
    {
        return self::createInstanceWithConstructor($data);
    }

    public static function testHydrateRemainingProperties(object $instance, array $data): static
    {
        return self::hydrateRemainingProperties($instance, $data);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}


readonly class TestEmptyClass extends GraniteVO
{
    use HasDeserialization;

    public static function testMapScalarToFirstProperty(mixed $value): array
    {
        return self::mapScalarToFirstProperty($value);
    }

    public static function testCreateInstanceWithConstructor(array $data): static
    {
        return self::createInstanceWithConstructor($data);
    }

    protected static function getClassConvention(string $class): ?NamingConvention
    {
        return null;
    }

    protected static function getClassDateTimeProvider(string $class): ?DateTimeProvider
    {
        return null;
    }

    protected static function findValueInData(
        array $data,
        string $phpName,
        string $serializedName,
        ?NamingConvention $convention,
    ): mixed {
        return $data[$phpName] ?? $data[$serializedName] ?? null;
    }

    protected static function convertValueToType(
        mixed $value,
        ?ReflectionType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        return $value;
    }
}

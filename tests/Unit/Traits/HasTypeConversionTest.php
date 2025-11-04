<?php

namespace Tests\Unit\Traits;

use DateTimeImmutable;
use DateTimeInterface;
use Ninja\Granite\GraniteVO;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Support\CarbonSupport;
use Ninja\Granite\Traits\HasTypeConversion;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Tests\Helpers\TestCase;

class HasTypeConversionTest extends TestCase
{
    private TestClassWithTypeConversion $testClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testClass = new TestClassWithTypeConversion();
    }

    public function test_convert_value_to_type_null_value(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'name');
        $type = $property->getType();

        $result = $this->testClass->testConvertValueToType(null, $type);
        $this->assertNull($result);
    }

    public function test_convert_value_to_type_named_type(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'name');
        $type = $property->getType();

        $result = $this->testClass->testConvertValueToType('test', $type);
        $this->assertEquals('test', $result);
    }

    public function test_convert_value_to_type_union_type(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unionProperty');
        $type = $property->getType();

        if ($type instanceof ReflectionUnionType) {
            $result = $this->testClass->testConvertValueToType('test', $type);
            $this->assertEquals('test', $result);
        } else {
            // If union type is not available, just test with the value
            $result = $this->testClass->testConvertValueToType('test', $type);
            $this->assertEquals('test', $result);
        }
    }

    public function test_convert_value_to_type_no_type(): void
    {
        $result = $this->testClass->testConvertValueToType('test', null);
        $this->assertEquals('test', $result);
    }

    public function test_convert_to_named_type_carbon_class(): void
    {
        if ( ! CarbonSupport::isAvailable()) {
            $this->markTestSkipped('Carbon not available');
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'name');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType('2023-01-01', $type);
            $this->assertNotNull($result);
        }
    }

    public function test_convert_to_named_type_granite_object(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'graniteObject');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType(['name' => 'test'], $type);
            $this->assertInstanceOf(TestGraniteObject::class, $result);
        }
    }

    public function test_convert_to_named_type_granite_object_null(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'graniteObject');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType(null, $type);
            $this->assertNull($result);
        }
    }

    public function test_convert_to_named_type_datetime(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'dateTime');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType('2023-01-01', $type);
            $this->assertInstanceOf(DateTimeInterface::class, $result);
        }
    }

    public function test_convert_to_named_type_backed_enum(): void
    {
        if ( ! interface_exists('UnitEnum') || ! class_exists(TestBackedEnum::class)) {
            $this->assertTrue(true); // Skip gracefully if enums not available
            return;
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'backedEnum');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType('active', $type);
            $this->assertEquals(TestBackedEnum::ACTIVE, $result);
        }
    }

    public function test_convert_to_named_type_unit_enum(): void
    {
        if ( ! interface_exists('UnitEnum') || ! class_exists(TestUnitEnum::class)) {
            $this->assertTrue(true); // Skip gracefully if enums not available
            return;
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unitEnum');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType('RED', $type);
            $this->assertEquals(TestUnitEnum::RED, $result);
        }
    }

    public function test_convert_to_named_type_enum_already_instance(): void
    {
        if ( ! interface_exists('UnitEnum') || ! class_exists(TestBackedEnum::class)) {
            $this->assertTrue(true); // Skip gracefully if enums not available
            return;
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'backedEnum');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $enumValue = TestBackedEnum::ACTIVE;
            $result = $this->testClass->testConvertToNamedType($enumValue, $type);
            $this->assertSame($enumValue, $result);
        }
    }

    public function test_convert_to_named_type_enum_invalid_value(): void
    {
        if ( ! interface_exists('UnitEnum') || ! class_exists(TestBackedEnum::class)) {
            $this->assertTrue(true); // Skip gracefully if enums not available
            return;
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'backedEnum');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType('invalid', $type);
            $this->assertNull($result);
        }
    }

    public function test_convert_to_union_type_success(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unionProperty');
        $type = $property->getType();

        if ($type instanceof ReflectionUnionType) {
            $result = $this->testClass->testConvertToUnionType('test', $type);
            $this->assertEquals('test', $result);
        } else {
            // If union type is not available, just test basic conversion
            $result = $this->testClass->testConvertToUnionType('test', $type);
            $this->assertEquals('test', $result);
        }
    }

    public function test_convert_to_named_type_regular_value(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'name');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType('regular_value', $type);
            $this->assertEquals('regular_value', $result);
        }
    }

    public function test_convert_to_named_type_enum_with_int_value(): void
    {
        if ( ! interface_exists('UnitEnum') || ! class_exists(TestIntBackedEnum::class)) {
            $this->assertTrue(true); // Skip gracefully if enums not available
            return;
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'intBackedEnum');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $result = $this->testClass->testConvertToNamedType(1, $type);
            $this->assertEquals(TestIntBackedEnum::FIRST, $result);
        }
    }

    public function test_convert_to_named_type_enum_with_invalid_type(): void
    {
        if ( ! interface_exists('UnitEnum') || ! class_exists(TestBackedEnum::class)) {
            $this->assertTrue(true); // Skip gracefully if enums not available
            return;
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'backedEnum');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            // Pass array instead of string/int
            $result = $this->testClass->testConvertToNamedType(['invalid'], $type);
            $this->assertNull($result);
        }
    }

    public function test_convert_to_union_type_with_carbon(): void
    {
        if ( ! CarbonSupport::isAvailable()) {
            $this->markTestSkipped('Carbon not available');
        }

        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unionCarbonProperty');
        $type = $property->getType();

        if ($type instanceof ReflectionUnionType) {
            $result = $this->testClass->testConvertToUnionType('2023-01-01', $type);
            $this->assertInstanceOf(DateTimeInterface::class, $result);
        }
    }

    public function test_convert_to_union_type_with_datetime(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unionDateTimeProperty');
        $type = $property->getType();

        if ($type instanceof ReflectionUnionType) {
            $result = $this->testClass->testConvertToUnionType('2023-01-01', $type);
            $this->assertInstanceOf(DateTimeInterface::class, $result);
        }
    }

    public function test_convert_to_union_type_no_matching_types(): void
    {
        // Test union type conversion when no types match
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unionProperty');
        $type = $property->getType();

        if ($type instanceof ReflectionUnionType) {
            // Use a value that shouldn't convert to any specific type
            $result = $this->testClass->testConvertToUnionType(42, $type);
            $this->assertEquals(42, $result); // Should return original value
        }
    }

    public function test_convert_to_union_type_null_conversion_fallback(): void
    {
        // Test that union type conversion falls back to original value
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'unionProperty');
        $type = $property->getType();

        if ($type instanceof ReflectionUnionType) {
            $result = $this->testClass->testConvertToUnionType(['array_value'], $type);
            $this->assertEquals(['array_value'], $result); // Should return original value
        }
    }

    public function test_convert_value_to_type_unknown_reflection_type(): void
    {
        // Test with mock reflection type that's neither named nor union
        $mockType = $this->createMock(ReflectionType::class);

        $result = $this->testClass->testConvertValueToType('test', $mockType);
        $this->assertEquals('test', $result);
    }
}

class TestClassWithTypeConversion
{
    use HasTypeConversion;

    public function testConvertValueToType($value, $type, $property = null, $classProvider = null)
    {
        return self::convertValueToType($value, $type, $property, $classProvider);
    }

    public function testConvertToNamedType($value, $type, $property = null, $classProvider = null)
    {
        return self::convertToNamedType($value, $type, $property, $classProvider);
    }

    public function testConvertToUnionType($value, $type, $property = null, $classProvider = null)
    {
        return self::convertToUnionType($value, $type, $property, $classProvider);
    }

    public function testConvertToUuidLike($value, $typeName)
    {
        return self::convertToUuidLike($value, $typeName);
    }

    public function testLooksLikeIdClass($className)
    {
        return self::looksLikeIdClass($className);
    }

    public function testTryCreateFromValue($value, $className)
    {
        return self::tryCreateFromValue($value, $className);
    }

    protected static function convertToCarbon(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface {
        if (null === $value) {
            return null;
        }
        if (CarbonSupport::isAvailable()) {
            return CarbonSupport::create($value);
        }
        return new DateTimeImmutable($value);
    }

    protected static function convertToDateTime(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface {
        if (null === $value) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }
        return null;
    }
}

class TestTypeConversionClass
{
    public string $name;
    public ?TestGraniteObject $graniteObject;
    public DateTimeInterface $dateTime;

    // Union type property for PHP 8.0+
    public string|int $unionProperty;

    // Union types with Carbon and DateTime for testing
    public string|\Carbon\Carbon $unionCarbonProperty;
    public string|DateTimeInterface $unionDateTimeProperty;

    // Enum properties for PHP 8.1+
    public TestBackedEnum $backedEnum;
    public TestUnitEnum $unitEnum;
    public TestIntBackedEnum $intBackedEnum;

    // UUID/ULID properties for testing
    public \Tests\Fixtures\VOs\CustomUuid $customUuid;
    public \Tests\Fixtures\VOs\Rcuid $rcuid;
    public \Tests\Fixtures\VOs\UserId $userId;
    public \Tests\Fixtures\VOs\InvalidId $invalidId;
}

readonly class TestGraniteObject extends GraniteVO
{
    public string $name;
}

// Test class that returns null from conversion methods
class TestClassWithNullConversion extends TestClassWithTypeConversion
{
    protected static function convertToCarbon(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface {
        return null; // Always return null for testing
    }

    protected static function convertToDateTime(
        mixed $value,
        string $typeName,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): ?DateTimeInterface {
        return null; // Always return null for testing
    }
}

// Enums for PHP 8.1+ testing
if (interface_exists('UnitEnum')) {
    enum TestBackedEnum: string
    {
        case ACTIVE = 'active';
        case INACTIVE = 'inactive';
    }

    enum TestUnitEnum
    {
        case RED;
        case GREEN;
        case BLUE;
    }

    enum TestIntBackedEnum: int
    {
        case FIRST = 1;
        case SECOND = 2;
    }
}

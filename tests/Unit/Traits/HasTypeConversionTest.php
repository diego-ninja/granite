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

    public function test_looks_like_id_class_uuid_variants(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // Should match "uuid"
        $this->assertTrue($testClass->testLooksLikeIdClass('CustomUuid'));
        $this->assertTrue($testClass->testLooksLikeIdClass('App\\OrderUuid'));

        // Should match "ulid"
        $this->assertTrue($testClass->testLooksLikeIdClass('Ulid'));
        $this->assertTrue($testClass->testLooksLikeIdClass('App\\Domain\\Ulid'));

        // Should match "uid"
        $this->assertTrue($testClass->testLooksLikeIdClass('Rcuid'));
        $this->assertTrue($testClass->testLooksLikeIdClass('CustomUid'));

        // Should match "id"
        $this->assertTrue($testClass->testLooksLikeIdClass('UserId'));
        $this->assertTrue($testClass->testLooksLikeIdClass('OrderId'));

        // Should not match - regular classes
        $this->assertFalse($testClass->testLooksLikeIdClass('Customer'));
        $this->assertFalse($testClass->testLooksLikeIdClass('OrderStatus'));
    }

    public function test_try_create_from_value_with_from_method(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testTryCreateFromValue(
            'user-123',
            \Tests\Fixtures\VOs\UserId::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\UserId::class, $result);
        $this->assertEquals('user-123', $result->value);
    }

    public function test_try_create_from_value_with_from_string_method(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testTryCreateFromValue(
            'rc-456',
            \Tests\Fixtures\VOs\Rcuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\Rcuid::class, $result);
        $this->assertEquals('rc-456', $result->value);
    }

    public function test_try_create_from_value_prefers_from_over_from_string(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // CustomUuid has both methods - should use from()
        $result = $testClass->testTryCreateFromValue(
            'custom-789',
            \Tests\Fixtures\VOs\CustomUuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\CustomUuid::class, $result);
        $this->assertEquals('custom-789', $result->value);
    }

    public function test_try_create_from_value_already_correct_type(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $userId = \Tests\Fixtures\VOs\UserId::from('existing');
        $result = $testClass->testTryCreateFromValue(
            $userId,
            \Tests\Fixtures\VOs\UserId::class
        );

        $this->assertSame($userId, $result);
    }

    public function test_try_create_from_value_handles_exceptions(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // InvalidId throws exceptions from both methods
        $result = $testClass->testTryCreateFromValue(
            'invalid',
            \Tests\Fixtures\VOs\InvalidId::class
        );

        // Should return original value unchanged
        $this->assertEquals('invalid', $result);
    }

    public function test_try_create_from_value_no_factory_methods(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // stdClass has no from() or fromString()
        $result = $testClass->testTryCreateFromValue(
            'test-value',
            \stdClass::class
        );

        // Should return original value
        $this->assertEquals('test-value', $result);
    }

    public function test_convert_to_uuid_like_custom_uuid(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'custom-uuid-123',
            \Tests\Fixtures\VOs\CustomUuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\CustomUuid::class, $result);
        $this->assertEquals('custom-uuid-123', $result->value);
    }

    public function test_convert_to_uuid_like_rcuid(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'rcuid-456',
            \Tests\Fixtures\VOs\Rcuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\Rcuid::class, $result);
        $this->assertEquals('rcuid-456', $result->value);
    }

    public function test_convert_to_uuid_like_user_id(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'user-789',
            \Tests\Fixtures\VOs\UserId::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\UserId::class, $result);
        $this->assertEquals('user-789', $result->value);
    }

    public function test_convert_to_uuid_like_non_id_class(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // TestGraniteObject doesn't match naming heuristic
        $result = $testClass->testConvertToUuidLike(
            'customer-data',
            \Tests\Fixtures\VOs\TestGraniteObject::class
        );

        // Should return original value unchanged
        $this->assertEquals('customer-data', $result);
    }

    public function test_convert_to_uuid_like_handles_conversion_failure(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'will-fail',
            \Tests\Fixtures\VOs\InvalidId::class
        );

        // Should return original value when conversion fails
        $this->assertEquals('will-fail', $result);
    }

    public function test_convert_to_uuid_like_ramsey_uuid(): void
    {
        if (!interface_exists('Ramsey\Uuid\UuidInterface')) {
            $this->markTestSkipped('ramsey/uuid not installed');
        }

        $testClass = new TestClassWithTypeConversion();

        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $testClass->testConvertToUuidLike(
            $uuidString,
            \Ramsey\Uuid\Uuid::class
        );

        $this->assertInstanceOf(\Ramsey\Uuid\UuidInterface::class, $result);
        $this->assertEquals($uuidString, $result->toString());
    }

    public function test_convert_to_uuid_like_symfony_uuid(): void
    {
        if (!class_exists('Symfony\Component\Uid\Uuid')) {
            $this->markTestSkipped('symfony/uid not installed');
        }

        $testClass = new TestClassWithTypeConversion();

        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $testClass->testConvertToUuidLike(
            $uuidString,
            \Symfony\Component\Uid\Uuid::class
        );

        $this->assertInstanceOf(\Symfony\Component\Uid\AbstractUid::class, $result);
        $this->assertEquals($uuidString, (string) $result);
    }

    public function test_convert_to_uuid_like_symfony_ulid(): void
    {
        if (!class_exists('Symfony\Component\Uid\Ulid')) {
            $this->markTestSkipped('symfony/uid not installed');
        }

        $testClass = new TestClassWithTypeConversion();

        $ulidString = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $result = $testClass->testConvertToUuidLike(
            $ulidString,
            \Symfony\Component\Uid\Ulid::class
        );

        $this->assertInstanceOf(\Symfony\Component\Uid\AbstractUid::class, $result);
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

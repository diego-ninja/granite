<?php

namespace Tests\Unit\Traits;

use JsonSerializable;
use Ninja\Granite\Granite;
use stdClass;
use Tests\Helpers\TestCase;

/**
 * Comprehensive tests for object hydration functionality.
 *
 * Covers:
 * - Phase 1: toArray(), JsonSerializable, public properties
 * - Phase 2: Smart getter extraction (getName, get_name, isActive, etc.)
 */
class ObjectHydrationTest extends TestCase
{
    // ========================================================================
    // Phase 1 Tests: Basic Object Extraction
    // ========================================================================

    public function test_from_with_object_having_toArray_method(): void
    {
        $source = new ClassWithToArray('John Doe', 'john@example.com', 30);
        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertEquals(30, $result->age);
    }

    public function test_from_with_jsonserializable_object(): void
    {
        $source = new ClassWithJsonSerializable('Jane Doe', 'jane@example.com', 25);
        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('Jane Doe', $result->name);
        $this->assertEquals('jane@example.com', $result->email);
        $this->assertEquals(25, $result->age);
    }

    public function test_from_with_object_having_public_properties(): void
    {
        $source = new ClassWithPublicProperties();
        $source->name = 'Bob Smith';
        $source->email = 'bob@example.com';
        $source->age = 35;

        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('Bob Smith', $result->name);
        $this->assertEquals('bob@example.com', $result->email);
        $this->assertEquals(35, $result->age);
    }

    public function test_from_with_object_having_mixed_private_public_properties(): void
    {
        $source = new ClassWithMixedProperties('Alice Brown', 'alice@example.com', 28);

        $result = TestHydrationTarget::from($source);

        // Only public properties should be extracted
        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('Alice Brown', $result->name);
        // email is private, so it won't be extracted
        // age is public, so it will be extracted
        $this->assertEquals(28, $result->age);
    }

    public function test_extract_data_from_object_with_toArray(): void
    {
        $source = new ClassWithToArray('Test', 'test@example.com', 40);
        $result = TestHydrationTarget::testExtractDataFromObject($source);

        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals(40, $result['age']);
    }

    public function test_extract_data_from_object_with_jsonserialize(): void
    {
        $source = new ClassWithJsonSerializable('Test', 'test@example.com', 50);
        $result = TestHydrationTarget::testExtractDataFromObject($source);

        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals(50, $result['age']);
    }

    public function test_extract_data_from_object_with_public_properties(): void
    {
        $source = new ClassWithPublicProperties();
        $source->name = 'Public Test';
        $source->email = 'public@example.com';
        $source->age = 60;

        $result = TestHydrationTarget::testExtractDataFromObject($source);

        $this->assertIsArray($result);
        $this->assertEquals('Public Test', $result['name']);
        $this->assertEquals('public@example.com', $result['email']);
        $this->assertEquals(60, $result['age']);
    }

    public function test_extract_data_from_granite_object(): void
    {
        $source = TestHydrationTarget::from(['name' => 'Granite', 'email' => 'granite@example.com', 'age' => 70]);
        $result = TestHydrationTarget::testExtractDataFromObject($source);

        $this->assertIsArray($result);
        $this->assertEquals('Granite', $result['name']);
        $this->assertEquals('granite@example.com', $result['email']);
        $this->assertEquals(70, $result['age']);
    }

    public function test_normalize_input_data_with_object(): void
    {
        $source = new ClassWithToArray('Normalize', 'normalize@example.com', 80);
        $result = TestHydrationTarget::testNormalizeInputData($source);

        $this->assertIsArray($result);
        $this->assertEquals('Normalize', $result['name']);
        $this->assertEquals('normalize@example.com', $result['email']);
        $this->assertEquals(80, $result['age']);
    }

    public function test_looks_like_structured_data_with_objects(): void
    {
        $this->assertTrue(TestHydrationTarget::testLooksLikeStructuredData(new ClassWithToArray('test', 'test@test.com', 1)));
        $this->assertTrue(TestHydrationTarget::testLooksLikeStructuredData(new ClassWithPublicProperties()));
        $this->assertTrue(TestHydrationTarget::testLooksLikeStructuredData(new ClassWithJsonSerializable('test', 'test@test.com', 1)));
    }

    public function test_from_with_stdClass_object(): void
    {
        $source = new stdClass();
        $source->name = 'StdClass Test';
        $source->email = 'std@example.com';
        $source->age = 45;

        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('StdClass Test', $result->name);
        $this->assertEquals('std@example.com', $result->email);
        $this->assertEquals(45, $result->age);
    }

    public function test_from_with_empty_object(): void
    {
        $source = new stdClass();
        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        // Properties should use defaults or remain uninitialized
    }

    public function test_priority_toArray_over_jsonserialize(): void
    {
        $source = new ClassWithBothMethods('Priority', 'priority@example.com', 55);
        $result = TestHydrationTarget::testExtractDataFromObject($source);

        // Should use toArray() method (returns data with 'from_toArray' marker)
        $this->assertIsArray($result);
        $this->assertEquals('from_toArray', $result['source']);
    }

    public function test_toArray_returns_non_array(): void
    {
        $source = new ClassWithBrokenToArray();
        $result = TestHydrationTarget::testExtractDataFromObject($source);

        // Should fallback to empty array when toArray() doesn't return array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_jsonserialize_returns_non_array(): void
    {
        $source = new ClassWithBrokenJsonSerialize();
        $result = TestHydrationTarget::testExtractDataFromObject($source);

        // Should fallback to empty array when jsonSerialize() doesn't return array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_from_with_object_with_nested_objects(): void
    {
        $address = new ClassWithPublicProperties();
        $address->street = '123 Main St';
        $address->city = 'New York';

        $source = new ClassWithPublicProperties();
        $source->name = 'Nested Test';
        $source->address = $address;

        $result = TestHydrationTargetWithNested::from($source);

        $this->assertInstanceOf(TestHydrationTargetWithNested::class, $result);
        $this->assertEquals('Nested Test', $result->name);
        // The nested address should be converted properly by type conversion
    }

    public function test_from_with_mixed_base_data_and_overrides(): void
    {
        $source = new ClassWithToArray('Base Name', 'base@example.com', 20);

        // Mix object data with named parameter override
        $result = TestHydrationTarget::from($source, age: 99);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('Base Name', $result->name);
        $this->assertEquals('base@example.com', $result->email);
        $this->assertEquals(99, $result->age); // Override should win
    }

    // ========================================================================
    // Phase 2 Tests: Smart Getter Extraction
    // ========================================================================

    public function test_extract_via_standard_camelcase_getters(): void
    {
        $source = new ClassWithStandardGetters('John Doe', 'john@example.com', 30);
        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertEquals(30, $result->age);
    }

    public function test_extract_via_snake_case_getters(): void
    {
        $source = new ClassWithSnakeCaseGetters('Jane Doe', 'jane@example.com', 25);
        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('Jane Doe', $result->name);
        $this->assertEquals('jane@example.com', $result->email);
        $this->assertEquals(25, $result->age);
    }

    public function test_extract_via_boolean_is_getters(): void
    {
        $source = new ClassWithBooleanGetters(true, false);
        $result = TestBooleanTarget::from($source);

        $this->assertInstanceOf(TestBooleanTarget::class, $result);
        $this->assertTrue($result->active);
        $this->assertFalse($result->verified);
    }

    public function test_extract_via_boolean_has_getters(): void
    {
        $source = new ClassWithHasGetters(true, false);
        $result = TestHasTarget::from($source);

        $this->assertInstanceOf(TestHasTarget::class, $result);
        $this->assertTrue($result->permission);
        $this->assertFalse($result->access);
    }

    public function test_extract_with_snake_case_property_names(): void
    {
        $source = new ClassWithSnakeCasePropertyGetters('Jane', 'Doe', 'jane.doe@example.com');
        $result = TestSnakeCaseTarget::from($source);

        $this->assertInstanceOf(TestSnakeCaseTarget::class, $result);
        $this->assertEquals('Jane', $result->first_name);
        $this->assertEquals('Doe', $result->last_name);
        $this->assertEquals('jane.doe@example.com', $result->email_address);
    }

    public function test_public_properties_take_precedence_over_getters(): void
    {
        $source = new ClassWithBothPublicAndGetters();
        $result = TestHydrationTarget::from($source);

        // Public property value should win
        $this->assertEquals('from_public', $result->name);
        // No public property, so getter is used
        $this->assertEquals('from_getter', $result->email);
    }

    public function test_getter_throws_exception_tries_next_pattern(): void
    {
        $source = new ClassWithThrowingGetter();
        $result = TestHydrationTarget::from($source);

        // Should skip the throwing getter and use fallback
        $this->assertInstanceOf(TestHydrationTarget::class, $result);
    }

    public function test_extract_with_mixed_public_and_getters(): void
    {
        $source = new ClassWithMixedAccess('Public Name', 'private@example.com', 40);
        $result = TestHydrationTarget::from($source);

        $this->assertEquals('Public Name', $result->name);
        $this->assertEquals('private@example.com', $result->email);
        $this->assertEquals(40, $result->age);
    }

    public function test_build_getter_patterns_standard_property(): void
    {
        $patterns = TestHydrationTarget::testBuildGetterPatterns('name', null);

        $this->assertContains('getName', $patterns);
        $this->assertContains('name', $patterns);
    }

    public function test_build_getter_patterns_snake_case_property(): void
    {
        $patterns = TestHydrationTarget::testBuildGetterPatterns('full_name', null);

        $this->assertContains('getFullName', $patterns);
        $this->assertContains('get_full_name', $patterns);
    }

    public function test_build_getter_patterns_is_prefix_property(): void
    {
        $patterns = TestHydrationTarget::testBuildGetterPatterns('isActive', null);

        $this->assertContains('getIsActive', $patterns);
        $this->assertContains('isActive', $patterns);
    }

    public function test_build_getter_patterns_has_prefix_property(): void
    {
        $patterns = TestHydrationTarget::testBuildGetterPatterns('hasPermission', null);

        $this->assertContains('getHasPermission', $patterns);
        $this->assertContains('hasPermission', $patterns);
    }

    public function test_snake_to_camel_conversion(): void
    {
        $this->assertEquals('fullName', TestHydrationTarget::testSnakeToCamel('full_name'));
        $this->assertEquals('firstName', TestHydrationTarget::testSnakeToCamel('first_name'));
        $this->assertEquals('isActive', TestHydrationTarget::testSnakeToCamel('is_active'));
        $this->assertEquals('name', TestHydrationTarget::testSnakeToCamel('name'));
    }

    public function test_extract_via_getters_empty_existing_data(): void
    {
        $source = new ClassWithStandardGetters('Test', 'test@example.com', 99);
        $result = TestHydrationTarget::testExtractViaGetters($source, []);

        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals(99, $result['age']);
    }

    public function test_extract_via_getters_with_existing_data(): void
    {
        $source = new ClassWithStandardGetters('New Name', 'new@example.com', 50);
        $existing = ['name' => 'Existing Name'];

        $result = TestHydrationTarget::testExtractViaGetters($source, $existing);

        // Should not override existing data
        $this->assertArrayNotHasKey('name', $result);
        // Should extract missing properties
        $this->assertEquals('new@example.com', $result['email']);
        $this->assertEquals(50, $result['age']);
    }

    public function test_from_object_with_only_getters_no_public_props(): void
    {
        $source = new ClassWithOnlyGetters('Getter User', 'getter@example.com', 35);
        $result = TestHydrationTarget::from($source);

        $this->assertInstanceOf(TestHydrationTarget::class, $result);
        $this->assertEquals('Getter User', $result->name);
        $this->assertEquals('getter@example.com', $result->email);
        $this->assertEquals(35, $result->age);
    }

    public function test_from_complex_object_with_nested_getters(): void
    {
        $source = new ClassWithNestedGetters();
        $result = TestComplexTarget::from($source);

        $this->assertInstanceOf(TestComplexTarget::class, $result);
        $this->assertEquals('Complex Name', $result->name);
        $this->assertEquals(100, $result->count);
    }

    public function test_getter_patterns_for_boolean_type(): void
    {
        $reflection = new \ReflectionProperty(TestBooleanTarget::class, 'active');
        $type = $reflection->getType();

        $patterns = TestHydrationTarget::testBuildGetterPatterns('active', $type);

        // Should include boolean-specific patterns
        $this->assertContains('getActive', $patterns);
        $this->assertContains('isActive', $patterns);
    }

    public function test_from_with_getters_and_named_overrides(): void
    {
        $source = new ClassWithStandardGetters('Base Name', 'base@example.com', 20);

        $result = TestHydrationTarget::from(
            $source,
            age: 99  // Override age from getter
        );

        $this->assertEquals('Base Name', $result->name);
        $this->assertEquals('base@example.com', $result->email);
        $this->assertEquals(99, $result->age);  // Override should win
    }
}

// ============================================================================
// Test Fixture Classes
// ============================================================================

/**
 * Class with toArray() method (like Laravel models, Doctrine entities, etc.)
 */
class ClassWithToArray
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age,
        ];
    }
}

/**
 * Class implementing JsonSerializable
 */
class ClassWithJsonSerializable implements JsonSerializable
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age,
        ];
    }
}

/**
 * Class with only public properties
 */
class ClassWithPublicProperties
{
    public string $name = '';
    public string $email = '';
    public int $age = 0;
    public mixed $street = '';
    public mixed $city = '';
    public mixed $address = null;
}

/**
 * Class with mixed private and public properties
 */
class ClassWithMixedProperties
{
    public string $name;
    public int $age;
    private string $email;

    public function __construct(string $name, string $email, int $age)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
    }
}

/**
 * Class with both toArray() and JsonSerializable to test priority
 */
class ClassWithBothMethods implements JsonSerializable
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age,
            'source' => 'from_toArray',
        ];
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age,
            'source' => 'from_jsonSerialize',
        ];
    }
}

/**
 * Class with broken toArray() that returns non-array
 */
class ClassWithBrokenToArray
{
    public function toArray(): string
    {
        return 'not an array';
    }
}

/**
 * Class with broken jsonSerialize() that returns non-array
 */
class ClassWithBrokenJsonSerialize implements JsonSerializable
{
    public function jsonSerialize(): string
    {
        return 'not an array';
    }
}

/**
 * Class with standard camelCase getters
 */
class ClassWithStandardGetters
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAge(): int
    {
        return $this->age;
    }
}

/**
 * Class with snake_case getters
 */
class ClassWithSnakeCaseGetters
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_email(): string
    {
        return $this->email;
    }

    public function get_age(): int
    {
        return $this->age;
    }
}

/**
 * Class with boolean isXxx() getters
 */
class ClassWithBooleanGetters
{
    public function __construct(
        private bool $active,
        private bool $verified,
    ) {}

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }
}

/**
 * Class with hasXxx() getters
 */
class ClassWithHasGetters
{
    public function __construct(
        private bool $permission,
        private bool $access,
    ) {}

    public function hasPermission(): bool
    {
        return $this->permission;
    }

    public function hasAccess(): bool
    {
        return $this->access;
    }
}

/**
 * Class with getters for snake_case properties
 */
class ClassWithSnakeCasePropertyGetters
{
    public function __construct(
        private string $firstName,
        private string $lastName,
        private string $emailAddress,
    ) {}

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }
}

/**
 * Class with both public properties and getters (public should win)
 */
class ClassWithBothPublicAndGetters
{
    public string $name = 'from_public';

    private string $email = 'from_private';

    public function getName(): string
    {
        return 'from_getter';
    }

    public function getEmail(): string
    {
        return 'from_getter';
    }
}

/**
 * Class where getter throws exception
 */
class ClassWithThrowingGetter
{
    public function getName(): string
    {
        throw new \RuntimeException('Getter failed');
    }
}

/**
 * Class with mixed public properties and private with getters
 */
class ClassWithMixedAccess
{
    public string $name;

    public function __construct(
        string $name,
        private string $email,
        private int $age,
    ) {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAge(): int
    {
        return $this->age;
    }
}

/**
 * Class with only getters, no public properties
 */
class ClassWithOnlyGetters
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAge(): int
    {
        return $this->age;
    }
}

/**
 * Class with nested structure
 */
class ClassWithNestedGetters
{
    public function getName(): string
    {
        return 'Complex Name';
    }

    public function getCount(): int
    {
        return 100;
    }
}

/**
 * Target Granite class for hydration tests
 */
final readonly class TestHydrationTarget extends Granite
{
    public function __construct(
        public string $name = '',
        public string $email = '',
        public int $age = 0,
    ) {}

    // Expose methods for testing
    public static function testExtractDataFromObject(object $source): array
    {
        return \Ninja\Granite\Hydration\HydratorFactory::getInstance()->hydrateWith($source, self::class);
    }

    public static function testNormalizeInputData(mixed $data): array
    {
        return self::normalizeInputData($data);
    }

    public static function testLooksLikeStructuredData(mixed $value): bool
    {
        return self::looksLikeStructuredData($value);
    }

    public static function testExtractViaGetters(object $source, array $existingData): array
    {
        $hydrator = new \Ninja\Granite\Hydration\Hydrators\GetterHydrator();
        return $hydrator->extractViaGetters($source, $existingData, self::class);
    }

    public static function testBuildGetterPatterns(string $propertyName, ?\ReflectionType $type): array
    {
        $hydrator = new \Ninja\Granite\Hydration\Hydrators\GetterHydrator();
        $reflection = new \ReflectionMethod($hydrator, 'buildGetterPatterns');
        $reflection->setAccessible(true);
        return $reflection->invoke($hydrator, $propertyName, $type);
    }

    public static function testSnakeToCamel(string $string): string
    {
        $hydrator = new \Ninja\Granite\Hydration\Hydrators\GetterHydrator();
        $reflection = new \ReflectionMethod($hydrator, 'snakeToCamel');
        $reflection->setAccessible(true);
        return $reflection->invoke($hydrator, $string);
    }
}

/**
 * Target class with nested object support
 */
final readonly class TestHydrationTargetWithNested extends Granite
{
    public function __construct(
        public string $name = '',
        public mixed $address = null,
    ) {}
}

final readonly class TestBooleanTarget extends Granite
{
    public function __construct(
        public bool $active = false,
        public bool $verified = false,
    ) {}
}

final readonly class TestHasTarget extends Granite
{
    public function __construct(
        public bool $permission = false,
        public bool $access = false,
    ) {}
}

final readonly class TestSnakeCaseTarget extends Granite
{
    public function __construct(
        public string $first_name = '',
        public string $last_name = '',
        public string $email_address = '',
    ) {}
}

final readonly class TestComplexTarget extends Granite
{
    public function __construct(
        public string $name = '',
        public int $count = 0,
    ) {}
}

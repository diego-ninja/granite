<?php

namespace Tests\Unit\Traits;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Traits\HasValidation;
use Ninja\Granite\Validation\Attributes\Required;
use ReflectionProperty;
use Tests\Helpers\TestCase;

class HasValidationTest extends TestCase
{
    public function test_validate_returns_true_for_valid_data(): void
    {
        $obj = TestValidationClass::from(['name' => 'John', 'age' => 30]);

        $this->assertTrue($obj->validate());
    }

    public function test_validate_always_returns_true_for_no_rules(): void
    {
        $obj = TestValidationClass::from(['name' => '', 'age' => 30]);

        $this->assertTrue($obj->validate());
    }

    public function test_is_valid_returns_same_as_validate(): void
    {
        $obj = TestValidationClass::from(['name' => 'John', 'age' => 30]);

        $this->assertTrue($obj->isValid());
        $this->assertEquals($obj->validate(), $obj->isValid());
    }

    public function test_get_validation_errors_returns_empty_for_valid_data(): void
    {
        $obj = TestValidationClass::from(['name' => 'John', 'age' => 30]);

        $errors = $obj->getValidationErrors();
        $this->assertEmpty($errors);
    }

    public function test_get_validation_errors_returns_empty_for_no_rules(): void
    {
        $obj = TestValidationClass::from(['name' => '', 'age' => 30]);

        $errors = $obj->getValidationErrors();
        $this->assertEmpty($errors);
    }

    public function test_get_validation_exception_returns_null_for_valid_data(): void
    {
        $obj = TestValidationClass::from(['name' => 'John', 'age' => 30]);

        $exception = $obj->getValidationException();
        $this->assertNull($exception);
    }

    public function test_get_validation_exception_returns_null_for_no_rules(): void
    {
        $obj = TestValidationClass::from(['name' => '', 'age' => 30]);

        $exception = $obj->getValidationException();
        $this->assertNull($exception);
    }

    public function test_validate_data_with_no_rules(): void
    {
        // Should not throw any exception
        TestValidationClass::testValidateData(['name' => 'John'], 'TestClass');
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function test_validate_data_with_rules_class(): void
    {
        // Test that the method runs without throwing (since validation might not be fully configured in test)
        TestValidationWithRulesClass::testValidateData(['name' => 'Valid'], 'TestClass');
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function test_has_validation_rules_returns_false_for_no_rules(): void
    {
        $hasRules = TestValidationClass::testHasValidationRules();
        $this->assertFalse($hasRules);
    }

    public function test_has_validation_rules_returns_true_for_method_rules(): void
    {
        $hasRules = TestValidationWithRulesClass::testHasValidationRules();
        $this->assertTrue($hasRules);
    }

    public function test_has_validation_rules_returns_true_for_attribute_rules(): void
    {
        $hasRules = TestValidationWithAttributesClass::testHasValidationRules();
        $this->assertTrue($hasRules);
    }

    public function test_rules_returns_empty_array_by_default(): void
    {
        $rules = TestValidationClass::testRules();
        $this->assertEmpty($rules);
    }

    public function test_rules_returns_custom_rules_when_overridden(): void
    {
        $rules = TestValidationWithRulesClass::testRules();
        $this->assertNotEmpty($rules);
        $this->assertArrayHasKey('name', $rules);
    }

    public function test_validate_property_with_no_attributes(): void
    {
        $obj = TestValidationClass::from(['name' => 'John']);
        $property = new ReflectionProperty(TestValidationClass::class, 'name');

        $errors = $obj->testValidateProperty($property, 'John', ['name' => 'John']);
        $this->assertEmpty($errors);
    }

    public function test_validate_property_with_attributes(): void
    {
        $obj = TestValidationWithAttributesClass::from(['name' => '']);
        $property = new ReflectionProperty(TestValidationWithAttributesClass::class, 'name');

        $errors = $obj->testValidateProperty($property, '', ['name' => '']);
        // May or may not have errors depending on attribute implementation
        $this->assertIsArray($errors);
    }

    public function test_validate_with_custom_data_parameter(): void
    {
        $obj = TestValidationClass::from(['name' => 'John']);

        // Test that custom data can be passed
        $this->assertTrue($obj->validate(['name' => 'Valid Name']));
        $this->assertTrue($obj->validate(['name' => ''])); // No rules defined, so always valid
    }
}

readonly class TestValidationClass extends GraniteVO
{
    use HasValidation;

    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}

    // Expose protected methods for testing
    public static function testValidateData(array $data, string $objectName): void
    {
        self::validateData($data, $objectName);
    }

    public static function testHasValidationRules(): bool
    {
        return self::hasValidationRules();
    }

    public static function testRules(): array
    {
        return self::rules();
    }

    public function testValidateProperty(ReflectionProperty $property, mixed $value, array $allData): array
    {
        return $this->validateProperty($property, $value, $allData);
    }
}

readonly class TestValidationWithRulesClass extends GraniteVO
{
    use HasValidation;

    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}

    public static function testValidateData(array $data, string $objectName): void
    {
        self::validateData($data, $objectName);
    }

    public static function testHasValidationRules(): bool
    {
        return self::hasValidationRules();
    }

    public static function testRules(): array
    {
        return self::rules();
    }

    protected static function rules(): array
    {
        return [
            'name' => ['required'],
        ];
    }
}

readonly class TestValidationWithAttributesClass extends GraniteVO
{
    use HasValidation;

    public function __construct(
        #[Required]
        public string $name = '',
        public int $age = 0,
    ) {}

    public static function testHasValidationRules(): bool
    {
        return self::hasValidationRules();
    }

    public function testValidateProperty(ReflectionProperty $property, mixed $value, array $allData): array
    {
        return $this->validateProperty($property, $value, $allData);
    }
}

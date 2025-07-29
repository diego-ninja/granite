<?php

// tests/Unit/GraniteVOTest.php

declare(strict_types=1);

namespace Tests\Unit;

use Error;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\ValidationException;
use Ninja\Granite\GraniteDTO;
use Ninja\Granite\GraniteVO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Fixtures\VOs\Address;
use Tests\Fixtures\VOs\MixedValidationVO;
use Tests\Fixtures\VOs\Money;
use Tests\Fixtures\VOs\ProductVO;
use Tests\Fixtures\VOs\UserVO;
use Tests\Fixtures\VOs\ValidatedUserVO;
use Tests\Helpers\TestCase;

#[CoversClass(GraniteVO::class)]
class GraniteVOTest extends TestCase
{
    public static function invalidUserDataProvider(): array
    {
        return [
            'empty name' => [
                ['name' => '', 'email' => 'john@example.com'],
                'name',
            ],
            'short name' => [
                ['name' => 'J', 'email' => 'john@example.com'],
                'name',
            ],
            'invalid email' => [
                ['name' => 'John Doe', 'email' => 'invalid'],
                'email',
            ],
            'missing email' => [
                ['name' => 'John Doe'],
                'email',
            ],
            'too young' => [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 15],
                'age',
            ],
            'too old' => [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 150],
                'age',
            ],
        ];
    }
    public function test_extends_granite_dto(): void
    {
        $vo = UserVO::from([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertInstanceOf(GraniteDTO::class, $vo);
        $this->assertInstanceOf(GraniteObject::class, $vo);
    }

    public function test_creates_valid_value_object(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $vo = UserVO::from($data);

        $this->assertEquals('John Doe', $vo->name);
        $this->assertEquals('john@example.com', $vo->email);
        $this->assertEquals(30, $vo->age);
    }

    public function test_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        ValidatedUserVO::from([
            'name' => '', // Required but empty
            'email' => 'john@example.com',
        ]);
    }

    public function test_validates_email_format(): void
    {
        $this->expectException(ValidationException::class);

        ValidatedUserVO::from([
            'name' => 'John Doe',
            'email' => 'invalid-email', // Invalid email format
        ]);
    }

    public function test_validates_age_constraints(): void
    {
        $this->expectException(ValidationException::class);

        ValidatedUserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 15, // Below minimum age of 18
        ]);
    }

    public function test_validates_name_length(): void
    {
        $this->expectException(ValidationException::class);

        ValidatedUserVO::from([
            'name' => 'J', // Too short (minimum 2 characters)
            'email' => 'john@example.com',
        ]);
    }

    public function test_validates_maximum_age(): void
    {
        $this->expectException(ValidationException::class);

        ValidatedUserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 150, // Above maximum age of 120
        ]);
    }

    public function test_validation_with_custom_error_messages(): void
    {
        try {
            ValidatedUserVO::from([
                'name' => '', // Required field with custom message
                'email' => 'john@example.com',
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $nameErrors = $e->getFieldErrors('name');
            $this->assertStringContainsString('Name must be at least 2 characters', $nameErrors[0]);
        }
    }

    public function test_validation_with_method_based_rules(): void
    {
        $this->expectException(ValidationException::class);

        ProductVO::from([
            'name' => 'Test Product',
            'sku' => 'INVALID', // Should be 10 alphanumeric characters
            'price' => 99.99,
            'quantity' => 10,
            'category' => 'electronics',
        ]);
    }

    public function test_validation_passes_with_valid_data(): void
    {
        $vo = ProductVO::from([
            'name' => 'Test Product',
            'sku' => 'ABCD123456', // Valid 10-character SKU
            'price' => 99.99,
            'quantity' => 10,
            'category' => 'electronics',
        ]);

        $this->assertEquals('Test Product', $vo->name);
        $this->assertEquals('ABCD123456', $vo->sku);
        $this->assertEquals(99.99, $vo->price);
    }

    public function test_equals_method_with_same_values(): void
    {
        $vo1 = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $vo2 = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $this->assertTrue($vo1->equals($vo2));
    }

    public function test_equals_method_with_different_values(): void
    {
        $vo1 = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $vo2 = UserVO::from([
            'name' => 'Jane Doe', // Different name
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $this->assertFalse($vo1->equals($vo2));
    }

    public function test_equals_method_with_same_instance(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertTrue($vo->equals($vo));
    }

    public function test_equals_method_with_different_classes(): void
    {
        $user = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $money = Money::from([
            'amount' => 100.0,
            'currency' => 'USD',
        ]);

        $this->assertFalse($user->equals($money));
    }

    public function test_equals_method_with_array(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $array = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $this->assertTrue($vo->equals($array));
    }

    public function test_equals_method_with_array_different_values(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $array = [
            'name' => 'Jane Doe', // Different name
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $this->assertFalse($vo->equals($array));
    }

    public function test_equals_method_with_array_extra_properties(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $array = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'extra_property' => 'extra_value',
        ];

        $this->assertFalse($vo->equals($array));
    }

    public function test_equals_method_with_array_missing_properties(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $array = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            // Missing age
        ];

        $this->assertFalse($vo->equals($array));
    }

    public function test_with_method_creates_new_instance(): void
    {
        $original = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $modified = $original->with(['name' => 'Jane Doe']);

        $this->assertNotSame($original, $modified);
        $this->assertEquals('John Doe', $original->name);
        $this->assertEquals('Jane Doe', $modified->name);
        $this->assertEquals($original->email, $modified->email);
        $this->assertEquals($original->age, $modified->age);
    }

    public function test_with_method_validates_modifications(): void
    {
        $original = ValidatedUserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $this->expectException(ValidationException::class);

        $original->with(['email' => 'invalid-email']);
    }

    public function test_with_method_allows_valid_modifications(): void
    {
        $original = ValidatedUserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $modified = $original->with([
            'name' => 'Jane Smith',
            'age' => 25,
        ]);

        $this->assertEquals('Jane Smith', $modified->name);
        $this->assertEquals(25, $modified->age);
        $this->assertEquals('john@example.com', $modified->email); // Unchanged
    }

    public function test_with_method_handles_multiple_modifications(): void
    {
        $original = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $modified = $original->with([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'age' => 25,
        ]);

        $this->assertEquals('Jane Smith', $modified->name);
        $this->assertEquals('jane@example.com', $modified->email);
        $this->assertEquals(25, $modified->age);
    }

    public function test_validation_merges_attribute_and_method_rules(): void
    {
        // MixedValidationVO has both attribute and method-based rules
        $this->expectException(ValidationException::class);

        MixedValidationVO::from([
            'title' => '', // Required via attribute
            'content' => 'short', // Min 10 chars via method
            'tags' => range(1, 10), // Max 5 items via method
            'status' => 'invalid', // Must be in specific values via method
        ]);
    }

    public function test_validation_method_rules_override_attributes(): void
    {
        // When both method and attribute rules exist for same property,
        // method rules should take precedence
        $vo = MixedValidationVO::from([
            'title' => 'Valid Title', // Attribute validation
            'content' => 'This is a long enough content string', // Method validation (min 10)
            'tags' => ['tag1', 'tag2'], // Method validation (max 5)
            'status' => 'published', // Method validation (must be in allowed values)
        ]);

        $this->assertEquals('Valid Title', $vo->title);
        $this->assertEquals('This is a long enough content string', $vo->content);
    }

    public function test_immutability_preserved_after_validation(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Should be readonly
        $reflection = new ReflectionClass($vo);
        $this->assertTrue($reflection->isReadonly());

        // Attempting to modify should fail
        $this->expectException(Error::class);
        $vo->name = 'Modified Name';
    }

    public function test_value_object_with_nested_value_objects(): void
    {
        $address = Address::from([
            'street' => '123 Main St',
            'city' => 'New York',
            'country' => 'USA',
            'zipCode' => '10001',
        ]);

        // Should validate nested value object
        $this->assertEquals('123 Main St', $address->street);
        $this->assertEquals('New York', $address->city);
    }

    public function test_value_object_serialization_preserves_validation(): void
    {
        $vo = ValidatedUserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $array = $vo->array();
        $json = $vo->json();

        // Serialization should work
        $this->assertIsArray($array);
        $this->assertJson($json);

        // Recreating from serialized data should also validate
        $newVo = ValidatedUserVO::from($array);
        $this->assertTrue($vo->equals($newVo));
    }

    #[DataProvider('invalidUserDataProvider')]
    public function test_validation_fails_for_invalid_data(array $data, string $expectedField): void
    {
        try {
            ValidatedUserVO::from($data);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldErrors($expectedField));
        }
    }

    public function test_performance_with_validation(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            ValidatedUserVO::from($data);
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 validations in reasonable time
        $this->assertLessThan(0.5, $elapsed, "VO creation with validation took too long: {$elapsed}s");
    }

    public function test_equals_performance_with_large_objects(): void
    {
        $largeData = ['name' => 'Test', 'email' => 'test@example.com'];
        for ($i = 0; $i < 100; $i++) {
            $largeData["field_{$i}"] = "value_{$i}";
        }

        $vo1 = UserVO::from($largeData);
        $vo2 = UserVO::from($largeData);

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $vo1->equals($vo2);
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 equality checks in reasonable time
        $this->assertLessThan(0.1, $elapsed, "Equals method took too long: {$elapsed}s");
    }

    public function test_with_method_performance(): void
    {
        $vo = UserVO::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            try {
                $vo->with(['age' => 30 + $i]);
            } catch (ValidationException $e) {
                // Ignore exceptions for performance test
            }
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 with() operations in reasonable time
        $this->assertLessThan(0.5, $elapsed, "With method took too long: {$elapsed}s");
    }

    public function test_validation_error_aggregation(): void
    {
        try {
            ValidatedUserVO::from([
                'name' => '', // Required error + min length error
                'email' => 'invalid', // Invalid email format
                'age' => 15, // Below minimum age
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            // Should have errors for all invalid fields
            $this->assertTrue($e->hasFieldErrors('name'));
            $this->assertTrue($e->hasFieldErrors('email'));
            $this->assertTrue($e->hasFieldErrors('age'));

            $allMessages = $e->getAllMessages();
            $this->assertGreaterThanOrEqual(3, count($allMessages));
        }
    }

    public function test_inheritance_maintains_validation(): void
    {
        // Value objects should inherit validation behavior from parent classes
        $this->assertTrue(is_subclass_of(ValidatedUserVO::class, GraniteVO::class));
        $this->assertTrue(is_subclass_of(UserVO::class, GraniteVO::class));
        $this->assertInstanceOf(GraniteVO::class, UserVO::from(['name' => 'Test', 'email' => 'test@example.com']));
    }
}

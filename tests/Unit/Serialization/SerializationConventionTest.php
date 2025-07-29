<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use InvalidArgumentException;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\MetadataCache;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DTOs\TestHiddenDto;
use Tests\Fixtures\DTOs\TestKebabCaseDto;
use Tests\Fixtures\DTOs\TestOrderDto;
use Tests\Fixtures\DTOs\TestOverrideDto;
use Tests\Fixtures\DTOs\TestSnakeCaseDto;
use Tests\Fixtures\DTOs\TestUnidirectionalDto;

class SerializationConventionTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear metadata cache before each test
        MetadataCache::clearCache();
    }

    public function testSnakeCaseConvention(): void
    {
        $dto = TestSnakeCaseDto::from([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
        ]);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->emailAddress);

        $serialized = $dto->array();
        $expected = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
        ];

        $this->assertEquals($expected, $serialized);
    }

    public function testKebabCaseConvention(): void
    {
        $dto = TestKebabCaseDto::from([
            'product-name' => 'Test Product',
            'unit-price' => 29.99,
            'is-available' => true,
        ]);

        $this->assertEquals('Test Product', $dto->productName);
        $this->assertEquals(29.99, $dto->unitPrice);
        $this->assertTrue($dto->isAvailable);

        $serialized = $dto->array();
        $expected = [
            'product-name' => 'Test Product',
            'unit-price' => 29.99,
            'is-available' => true,
        ];

        $this->assertEquals($expected, $serialized);
    }

    public function testSerializedNameOverridesConvention(): void
    {
        $dto = TestOverrideDto::from([
            'first_name' => 'John',
            'custom_last' => 'Doe',  // This uses the SerializedName override
            'email_address' => 'john@example.com',
        ]);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->emailAddress);

        $serialized = $dto->array();
        $expected = [
            'first_name' => 'John',
            'custom_last' => 'Doe',      // Override preserved
            'email_address' => 'john@example.com',
        ];

        $this->assertEquals($expected, $serialized);
    }

    public function testHiddenPropertyWithConvention(): void
    {
        $dto = TestHiddenDto::from([
            'public_field' => 'visible',
            'secret_field' => 'hidden',
        ]);

        $this->assertEquals('visible', $dto->publicField);
        $this->assertEquals('hidden', $dto->secretField);

        $serialized = $dto->array();
        $expected = [
            'public_field' => 'visible',
            // secret_field should not be present
        ];

        $this->assertEquals($expected, $serialized);
        $this->assertArrayNotHasKey('secret_field', $serialized);
    }

    public function testBidirectionalFlag(): void
    {
        // Bidirectional = false means convention only applies to serialization
        $dto = TestUnidirectionalDto::from([
            'testField' => 'value',  // camelCase input should work
        ]);

        $this->assertEquals('value', $dto->testField);

        $serialized = $dto->array();
        $expected = [
            'test_field' => 'value',  // But output should be snake_case
        ];

        $this->assertEquals($expected, $serialized);
    }

    public function testNestedDtosWithDifferentConventions(): void
    {
        $orderData = [
            'order_number' => 'ORD-123',
            'customer_info' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ],
            'total_amount' => 99.99,
        ];

        $dto = TestOrderDto::from($orderData);

        $this->assertEquals('ORD-123', $dto->orderNumber);
        $this->assertEquals('Jane', $dto->customerInfo->firstName);
        $this->assertEquals('Smith', $dto->customerInfo->lastName);
        $this->assertEquals(99.99, $dto->totalAmount);

        $serialized = $dto->array();

        $this->assertEquals('ORD-123', $serialized['order_number']);
        $this->assertEquals('Jane', $serialized['customer_info']['first_name']);
        $this->assertEquals(99.99, $serialized['total_amount']);
    }

    public function testInvalidConventionClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Convention class 'NonExistentConvention' does not exist");

        $attribute = new SerializationConvention('NonExistentConvention');
        $attribute->getConvention();
    }

    public function testFallbackWhenConventionFails(): void
    {
        // Test graceful degradation when convention application fails
        $dto = TestSnakeCaseDto::from([
            'firstName' => 'John',    // camelCase should still work as fallback
            'last_name' => 'Doe',      // snake_case should work via convention
        ]);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
    }

    public function testJsonStringInput(): void
    {
        $json = '{"first_name": "John", "last_name": "Doe", "email_address": "john@example.com"}';

        $dto = TestSnakeCaseDto::from($json);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->emailAddress);
    }

    public function testArrayToJsonConversion(): void
    {
        $dto = TestSnakeCaseDto::from([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
        ]);

        $json = $dto->json();
        $decoded = json_decode($json, true);

        $expected = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
        ];

        $this->assertEquals($expected, $decoded);
    }
}

<?php
// tests/Unit/GraniteDTOTest.php

declare(strict_types=1);

namespace Tests\Unit;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Exceptions\SerializationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\TestCase;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\SerializableDTO;
use Tests\Fixtures\DTOs\ComplexDTO;
use Tests\Fixtures\DTOs\NestedDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Fixtures\VOs\Address;

#[CoversClass(GraniteDTO::class)]
class GraniteDTOTest extends TestCase
{
    public function test_implements_granite_object_interface(): void
    {
        $dto = SimpleDTO::from(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']);

        $this->assertInstanceOf(GraniteObject::class, $dto);
    }

    public function test_creates_instance_from_array(): void
    {
        $data = [
            'id' => 42,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ];

        $dto = SimpleDTO::from($data);

        $this->assertEquals(42, $dto->id);
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals(30, $dto->age);
    }

    public function test_creates_instance_from_json_string(): void
    {
        $json = '{"id": 1, "name": "Jane", "email": "jane@example.com", "age": 25}';

        $dto = SimpleDTO::from($json);

        $this->assertEquals(1, $dto->id);
        $this->assertEquals('Jane', $dto->name);
        $this->assertEquals('jane@example.com', $dto->email);
        $this->assertEquals(25, $dto->age);
    }

    public function test_creates_instance_from_another_granite_object(): void
    {
        $original = SimpleDTO::from([
            'id' => 1,
            'name' => 'Original',
            'email' => 'original@example.com'
        ]);

        $copy = SimpleDTO::from($original);

        $this->assertEquals($original->id, $copy->id);
        $this->assertEquals($original->name, $copy->name);
        $this->assertEquals($original->email, $copy->email);
        $this->assertNotSame($original, $copy); // Different instances
    }

    public function test_handles_missing_properties_gracefully(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test'
            // Missing email and age
        ];

        $dto = SimpleDTO::from($data);

        $this->assertEquals(1, $dto->id);
        $this->assertEquals('Test', $dto->name);
        // email and age should be uninitialized (PHP 8 behavior)
    }

    public function test_handles_extra_properties_gracefully(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'age' => 30,
            'extra_property' => 'ignored'
        ];

        $dto = SimpleDTO::from($data);

        // Should only map known properties
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('Test', $dto->name);
        $this->assertEquals('test@example.com', $dto->email);
        $this->assertEquals(30, $dto->age);
    }

    public function test_converts_datetime_from_string(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'createdAt' => '2024-01-01T10:00:00Z'
        ];

        $dto = ComplexDTO::from($data);

        $this->assertInstanceOf(\DateTimeInterface::class, $dto->createdAt);
        $this->assertEquals('2024-01-01T10:00:00+00:00', $dto->createdAt->format('c'));
    }

    public function test_converts_enum_from_string(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'status' => 'active'
        ];

        $dto = ComplexDTO::from($data);

        $this->assertInstanceOf(UserStatus::class, $dto->status);
        $this->assertEquals(UserStatus::ACTIVE, $dto->status);
    }

    public function test_converts_nested_granite_objects(): void
    {
        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'country' => 'USA',
                'zipCode' => '10001'
            ]
        ];

        $dto = UserDTO::from($data);

        $this->assertEquals('John Doe', $dto->name);
        $this->assertInstanceOf(Address::class, $dto->address);
        $this->assertEquals('123 Main St', $dto->address->street);
        $this->assertEquals('New York', $dto->address->city);
    }

    public function test_serializes_to_array(): void
    {
        $dto = SimpleDTO::from([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'age' => 30
        ]);

        $array = $dto->array();

        $this->assertEquals([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'age' => 30
        ], $array);
    }

    public function test_serializes_to_json(): void
    {
        $dto = SimpleDTO::from([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com'
        ]);

        $json = $dto->json();
        $decoded = json_decode($json, true);

        $this->assertJson($json);
        $this->assertEquals(1, $decoded['id']);
        $this->assertEquals('Test', $decoded['name']);
        $this->assertEquals('test@example.com', $decoded['email']);
    }

    public function test_respects_serialized_names(): void
    {
        $dto = SerializableDTO::from([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret'
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertEquals('John', $array['first_name']);
        $this->assertEquals('Doe', $array['last_name']);
    }

    public function test_respects_hidden_properties(): void
    {
        $dto = SerializableDTO::from([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'apiToken' => 'token123'
        ]);

        $array = $dto->array();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('apiToken', $array);
        $this->assertArrayHasKey('email', $array);
    }

    public function test_serializes_datetime_objects(): void
    {
        $dateTime = new \DateTimeImmutable('2024-01-01T10:00:00Z');

        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => $dateTime->format('c')
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('createdAt', $array);
        $this->assertIsString($array['createdAt']);
        $this->assertEquals('2024-01-01T10:00:00+00:00', $array['createdAt']);
    }

    public function test_serializes_enum_objects(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'status' => UserStatus::ACTIVE
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('status', $array);
        $this->assertEquals('active', $array['status']);
    }

    public function test_serializes_nested_granite_objects(): void
    {
        $dto = UserDTO::from([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'country' => 'USA',
                'zipCode' => '10001'
            ]
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('address', $array);
        $this->assertIsArray($array['address']);
        $this->assertEquals('123 Main St', $array['address']['street']);
        $this->assertEquals('New York', $array['address']['city']);
    }

    public function test_handles_null_values_in_serialization(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => null,
            'status' => null
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertNull($array['createdAt']);
        $this->assertNull($array['status']);
    }

    public function test_throws_exception_for_unsupported_serialization_types(): void
    {
        // Create a DTO with an unsupported type (resource)
        $dto = new readonly class extends GraniteDTO {
            public mixed $resource;

            public function __construct() {
                $this->resource = fopen('php://memory', 'r');
            }
        };

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Cannot serialize property "resource"');

        $dto->array();
    }

    public function test_handles_malformed_json_input(): void
    {
        $this->expectException(\TypeError::class);

        SimpleDTO::from('{"invalid": json}');
    }

    public function test_handles_invalid_datetime_strings(): void
    {
        $this->expectException(\DateMalformedStringException::class);

        ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => 'invalid-date-string'
        ]);
    }

    public function test_skips_uninitialized_properties_in_serialization(): void
    {
        // Create a DTO where some properties might not be initialized
        $reflection = new \ReflectionClass(SimpleDTO::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        // Only set some properties
        $reflection->getProperty('id')->setValue($instance, 1);
        $reflection->getProperty('name')->setValue($instance, 'Test');
        // Leave email and age uninitialized

        $array = $instance->array();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        // Uninitialized properties should not appear
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('age', $array);
    }

    #[DataProvider('serializedNamesMappingProvider')]
    public function test_serialized_names_mapping(array $inputData, string $phpProperty, string $serializedName): void
    {
        $dto = SerializableDTO::from($inputData);
        $array = $dto->array();

        $this->assertArrayHasKey($serializedName, $array);
        $this->assertArrayNotHasKey($phpProperty, $array);
        $this->assertEquals($inputData[$phpProperty] ?? $inputData[$serializedName], $array[$serializedName]);
    }

    public static function serializedNamesMappingProvider(): array
    {
        return [
            'firstName to first_name' => [
                ['firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com', 'password' => 'secret'],
                'firstName',
                'first_name'
            ],
            'lastName to last_name' => [
                ['firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com', 'password' => 'secret'],
                'lastName',
                'last_name'
            ],
        ];
    }

    public function test_roundtrip_conversion(): void
    {
        $originalData = [
            'id' => 42,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 30
        ];

        $dto = SimpleDTO::from($originalData);
        $serialized = $dto->array();
        $newDto = SimpleDTO::from($serialized);

        $this->assertEquals($dto->id, $newDto->id);
        $this->assertEquals($dto->name, $newDto->name);
        $this->assertEquals($dto->email, $newDto->email);
        $this->assertEquals($dto->age, $newDto->age);
    }

    public function test_performance_with_large_objects(): void
    {
        $largeData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeData["property_$i"] = "value_$i";
        }
        $largeData['id'] = 1;
        $largeData['name'] = 'Test';
        $largeData['email'] = 'test@example.com';

        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $dto = SimpleDTO::from($largeData);
            $dto->array();
        }

        $elapsed = microtime(true) - $start;

        // Should complete 100 operations in reasonable time
        $this->assertLessThan(0.1, $elapsed, "DTO operations took too long: {$elapsed}s");
    }

    public function test_readonly_properties(): void
    {
        $dto = SimpleDTO::from([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com'
        ]);

        // Properties should be readonly - attempting to modify should fail
        $this->expectException(\Error::class);
        $dto->name = 'Modified'; // This should fail
    }

    public function test_handles_complex_nested_structures(): void
    {
        $complexData = [
            'id' => 1,
            'title' => 'Complex Post',
            'author' => [
                'id' => 1,
                'name' => 'Author Name',
                'email' => 'author@example.com',
                'address' => [
                    'street' => '123 Author St',
                    'city' => 'Author City',
                    'country' => 'Author Country',
                    'zipCode' => '12345'
                ]
            ],
            'tags' => ['php', 'testing', 'granite']
        ];

        $dto = NestedDTO::from($complexData);

        $this->assertEquals('Complex Post', $dto->title);
        $this->assertInstanceOf(UserDTO::class, $dto->author);
        $this->assertEquals('Author Name', $dto->author->name);
        $this->assertInstanceOf(Address::class, $dto->author->address);
        $this->assertEquals('123 Author St', $dto->author->address->street);
        $this->assertEquals(['php', 'testing', 'granite'], $dto->tags);
    }

    public function test_supports_union_types(): void
    {
        // Test with union types if they exist in test fixtures
        $data = [
            'flexibleDate' => '2024-01-01T10:00:00Z'
        ];

        $dto = \Tests\Fixtures\DTOs\DateDTO::from($data);

        $this->assertInstanceOf(\DateTimeInterface::class, $dto->flexibleDate);
    }

    public function test_is_immutable(): void
    {
        $dto = SimpleDTO::from([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com'
        ]);

        // DTO should be readonly
        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadonly());
    }

    public function test_inheritance_works_properly(): void
    {
        $this->assertTrue(is_subclass_of(SimpleDTO::class, GraniteDTO::class));
        $this->assertInstanceOf(GraniteDTO::class, SimpleDTO::from(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']));
    }
}
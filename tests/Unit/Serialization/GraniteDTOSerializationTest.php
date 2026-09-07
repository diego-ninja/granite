<?php

// tests/Unit/Serialization/GraniteDTOSerializationTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\SerializationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Fixtures\DTOs\AliasedValidatedDTO;
use Tests\Fixtures\DTOs\ComplexDTO;
use Tests\Fixtures\DTOs\NestedSerializationDTO;
use Tests\Fixtures\DTOs\PersonDTO;
use Tests\Fixtures\DTOs\SerializableDTO;
use Tests\Fixtures\Enums\Color;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Helpers\TestCase;

/**
 * @group serialization
 */
#[CoversClass(GraniteDTO::class)] class GraniteDTOSerializationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SerializationCache::clear();
    }

    protected function tearDown(): void
    {
        SerializationCache::clear();
        parent::tearDown();
    }

    public function test_serializes_simple_dto_to_array(): void
    {
        $dto = SerializableDTO::from([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $array = $dto->array();

        $this->assertSame([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ], $array);
    }

    public function test_serializes_validated_scalar_dto_with_method_metadata(): void
    {
        $dto = new AliasedValidatedDTO('Ada', 'secret-token');

        $this->assertSame(['display_name' => 'Ada'], $dto->array());
    }

    public function test_serializes_to_json(): void
    {
        $dto = SerializableDTO::from([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $json = $dto->json();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ], $decoded);
    }

    public function test_handles_null_values_in_serialization(): void
    {
        $dto = SerializableDTO::from([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'apiToken' => null,
        ]);

        $array = $dto->array();

        // apiToken is hidden, so shouldn't appear even if null
        $this->assertArrayNotHasKey('apiToken', $array);
    }

    public function test_serializes_datetime_objects(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-01T10:00:00Z');

        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => $dateTime->format('c'),
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('createdAt', $array);
        $this->assertIsString($array['createdAt']);
        $this->assertEquals('2024-01-01T10:00:00+00:00', $array['createdAt']);
    }

    public function test_serializes_enum_values(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'status' => UserStatus::ACTIVE,
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('status', $array);
        $this->assertEquals('active', $array['status']);
    }

    public function test_serializes_nested_arrays(): void
    {
        $metadata = [
            'tags' => ['php', 'testing'],
            'settings' => ['debug' => true, 'timeout' => 30],
        ];

        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'metadata' => $metadata,
        ]);

        $array = $dto->array();

        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals($metadata, $array['metadata']);
    }

    public function test_serializes_nested_arrays_recursively_with_original_keys(): void
    {
        $date = new DateTimeImmutable('2024-01-01T10:00:00.123456Z');
        $dto = new NestedSerializationDTO([
            PersonDTO::from(name: 'John', age: 30, email: 'john@example.com'),
            [
                'status' => UserStatus::ACTIVE,
                'color' => Color::RED,
                'date' => $date,
                'carbon' => Carbon::parse('2024-01-02T11:00:00Z'),
                'nullable' => null,
                'scalar' => 42,
            ],
        ]);

        $array = $dto->array();

        $this->assertSame('John', $array['items'][0]['name']);
        $this->assertSame('active', $array['items'][1]['status']);
        $this->assertSame('RED', $array['items'][1]['color']);
        $this->assertSame('2024-01-01T10:00:00+00:00', $array['items'][1]['date']);
        $this->assertSame('2024-01-02T11:00:00+00:00', $array['items'][1]['carbon']);
        $this->assertNull($array['items'][1]['nullable']);
        $this->assertSame(42, $array['items'][1]['scalar']);
    }

    public function test_json_contains_no_php_objects_after_recursive_serialization(): void
    {
        $dto = new NestedSerializationDTO([
            ['object' => PersonDTO::from(name: 'John', age: 30, email: 'john@example.com')],
            ['status' => UserStatus::ACTIVE, 'date' => new DateTimeImmutable('2024-01-01T10:00:00Z')],
        ]);

        $decoded = json_decode($dto->json(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('John', $decoded['items'][0]['object']['name']);
        $this->assertSame('active', $decoded['items'][1]['status']);
        $this->assertSame('2024-01-01T10:00:00+00:00', $decoded['items'][1]['date']);
    }

    public function test_mutable_carbon_is_not_cached(): void
    {
        $carbon = Carbon::parse('2024-01-01 12:00:00');
        $dto = new readonly class ($carbon) extends GraniteDTO {
            public function __construct(public Carbon $createdAt) {}
        };

        $first = $dto->array();
        $carbon->addDay();
        $second = $dto->array();

        $this->assertSame('2024-01-01T12:00:00+00:00', $first['createdAt']);
        $this->assertSame('2024-01-02T12:00:00+00:00', $second['createdAt']);
        $this->assertNull(SerializationCache::get($dto));
    }

    public function test_arrays_bypass_serialization_cache(): void
    {
        $carbon = Carbon::parse('2024-01-01 12:00:00');
        $dto = new NestedSerializationDTO(['date' => $carbon]);

        $dto->array();
        $carbon->addDay();

        $this->assertSame('2024-01-02T12:00:00+00:00', $dto->array()['items']['date']);
        $this->assertNull(SerializationCache::get($dto));
    }

    public function test_scalar_dto_uses_serialization_cache(): void
    {
        $dto = new PersonDTO('Ada', 37, 'ada@example.com');

        $first = $dto->array();
        $second = $dto->array();

        $this->assertSame($first, $second);
        $this->assertSame($first, SerializationCache::get($dto));
    }

    public function test_nested_immutable_graph_uses_serialization_cache(): void
    {
        $dto = new readonly class (new PersonDTO('Ada', 37, 'ada@example.com'), new DateTimeImmutable('2024-01-01T00:00:00+00:00')) extends GraniteDTO {
            public function __construct(
                public PersonDTO $person,
                public DateTimeImmutable $createdAt,
            ) {}
        };

        $result = $dto->array();

        $this->assertSame($result, SerializationCache::get($dto));
    }

    public function test_unsupported_nested_value_reports_full_property_path(): void
    {
        $dto = new NestedSerializationDTO([
            ['payload' => 'ok'],
            ['payload' => null],
            ['payload' => new stdClass()],
        ]);

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('items.2.payload');

        $dto->array();
    }

    public function test_skips_uninitialized_properties(): void
    {
        $instance = new \Tests\Fixtures\DTOs\UninitializedDTO('Test');
        $array = $instance->array();

        $this->assertSame([
            'name' => 'Test',
            'description' => null,
        ], $array);
    }

    public function test_throws_exception_for_unsupported_types(): void
    {
        $instance = new \Tests\Fixtures\DTOs\ResourceDTO();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Cannot serialize property "resource"');

        $instance->array();
    }

    public function test_deserializes_from_array_with_custom_names(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $dto = SerializableDTO::from($data);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals('secret123', $dto->password);
    }

    public function test_deserializes_from_php_names(): void
    {
        $data = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $dto = SerializableDTO::from($data);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals('secret123', $dto->password);
    }

    public function test_handles_both_php_and_serialized_names(): void
    {
        $data = [
            'firstName' => 'Jane',     // PHP name
            'first_name' => 'John',    // Serialized name
            'lastName' => 'Smith',     // PHP name only
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $dto = SerializableDTO::from($data);

        $this->assertSame('Jane', $dto->firstName);
        $this->assertSame('Smith', $dto->lastName);
        $this->assertSame('john@example.com', $dto->email);
    }

    public function test_serialized_name_takes_precedence_when_only_serialized_provided(): void
    {
        $data = [
            'first_name' => 'John',    // Only serialized name provided
            'last_name' => 'Doe',      // Only serialized name provided
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $dto = SerializableDTO::from($data);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_php_name_works_when_only_php_provided(): void
    {
        $data = [
            'firstName' => 'John',     // Only PHP name provided
            'lastName' => 'Doe',       // Only PHP name provided
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $dto = SerializableDTO::from($data);

        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_deserializes_datetime_from_string(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => '2024-01-01T10:00:00Z',
        ]);

        $this->assertInstanceOf(DateTimeInterface::class, $dto->createdAt);
        $this->assertEquals('2024-01-01T10:00:00+00:00', $dto->createdAt->format('c'));
    }

    public function test_deserializes_enum_from_string(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(UserStatus::class, $dto->status);
        $this->assertEquals(UserStatus::ACTIVE, $dto->status);
    }

    public function test_roundtrip_serialization(): void
    {
        $originalData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $dto = SerializableDTO::from($originalData);
        $serialized = $dto->array();

        // Password should be hidden in serialization
        $this->assertArrayNotHasKey('password', $serialized);

        // Serialized names should be used in output
        $this->assertArrayHasKey('first_name', $serialized);
        $this->assertArrayHasKey('last_name', $serialized);
        $this->assertEquals('John', $serialized['first_name']);
        $this->assertEquals('Doe', $serialized['last_name']);

        // Add password back for roundtrip test
        $serialized['password'] = 'secret123';

        $newDto = SerializableDTO::from($serialized);

        // Values should be preserved
        $this->assertEquals($dto->firstName, $newDto->firstName);
        $this->assertEquals($dto->lastName, $newDto->lastName);
        $this->assertEquals($dto->email, $newDto->email);
        $this->assertEquals($dto->password, $newDto->password);
    }

    public function test_serialization_performance(): void
    {
        $dto = SerializableDTO::from([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        // Warm up
        $dto->array();

        $iterations = 1000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $dto->array();
        }

        $elapsed = microtime(true) - $start;
        $avgTime = $elapsed / $iterations;

        // Should be very fast (less than 1ms per serialization)
        $this->assertLessThan(0.001, $avgTime, "Serialization too slow: {$avgTime}s per operation");
    }
}

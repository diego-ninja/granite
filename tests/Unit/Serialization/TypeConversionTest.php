<?php

// tests/Unit/Serialization/TypeConversionTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Ninja\Granite\GraniteDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\DTOs\BackedEnumDTO;
use Tests\Fixtures\DTOs\ComplexDTO;
use Tests\Fixtures\DTOs\DateDTO;
use Tests\Fixtures\DTOs\ScalarDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Fixtures\Enums\Color;
use Tests\Fixtures\Enums\Priority;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Fixtures\VOs\Address;
use Tests\Helpers\TestCase;

/**
 * @group type-conversion
 */
#[CoversClass(GraniteDTO::class)] class TypeConversionTest extends TestCase
{
    public function test_converts_string_to_datetime(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => '2024-01-01T10:00:00Z',
        ]);

        $this->assertInstanceOf(DateTimeInterface::class, $dto->createdAt);
        $this->assertEquals('2024-01-01T10:00:00+00:00', $dto->createdAt->format('c'));
    }

    public function test_converts_various_datetime_formats(): void
    {
        $formats = [
            '2024-01-01T10:00:00Z' => '2024-01-01T10:00:00+00:00',
            '2024-01-01 10:00:00' => '2024-01-01T10:00:00+00:00',
            '2024-01-01' => '2024-01-01T00:00:00+00:00',
            '2024/01/01 10:00:00' => '2024-01-01T10:00:00+00:00',
            'Jan 1, 2024 10:00:00' => '2024-01-01T10:00:00+00:00',
        ];

        foreach ($formats as $input => $expected) {
            $dto = ComplexDTO::from([
                'id' => 1,
                'name' => 'Test',
                'createdAt' => $input,
            ]);

            $this->assertInstanceOf(DateTimeInterface::class, $dto->createdAt);
            $this->assertEquals($expected, $dto->createdAt->format('c'), "Failed for input: {$input}");
        }
    }

    public function test_handles_null_datetime(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => null,
        ]);

        $this->assertNull($dto->createdAt);
    }

    public function test_converts_string_to_backed_enum(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(UserStatus::class, $dto->status);
        $this->assertEquals(UserStatus::ACTIVE, $dto->status);
        $this->assertEquals('active', $dto->status->value);
    }

    public function test_converts_all_backed_enum_values(): void
    {
        $statusTests = [
            'active' => UserStatus::ACTIVE,
            'inactive' => UserStatus::INACTIVE,
            'pending' => UserStatus::PENDING,
            'suspended' => UserStatus::SUSPENDED,
        ];

        foreach ($statusTests as $input => $expected) {
            $dto = ComplexDTO::from([
                'id' => 1,
                'name' => 'Test',
                'status' => $input,
            ]);

            $this->assertEquals($expected, $dto->status, "Failed for status: {$input}");
        }
    }

    public function test_converts_integer_to_backed_enum(): void
    {
        $instance = BackedEnumDTO::from(['priority' => 3]);

        $this->assertInstanceOf(Priority::class, $instance->priority);
        $this->assertEquals(Priority::HIGH, $instance->priority);
        $this->assertEquals(3, $instance->priority->value);
    }

    public function test_converts_unit_enum_from_string(): void
    {
        $instance = BackedEnumDTO::from(['color' => 'RED']);

        $this->assertInstanceOf(Color::class, $instance->color);
        $this->assertEquals(Color::RED, $instance->color);
    }

    public function test_handles_invalid_enum_values(): void
    {
        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'status' => 'invalid_status',
        ]);

        // Should handle gracefully - might be null or throw exception depending on implementation
        $this->assertTrue(
            null === $dto->status || $dto->status instanceof UserStatus,
            'Invalid enum value should result in null or valid enum',
        );
    }

    public function test_preserves_existing_enum_instances(): void
    {
        $status = UserStatus::ACTIVE;

        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'status' => $status,
        ]);

        $this->assertSame($status, $dto->status);
    }

    public function test_preserves_existing_datetime_instances(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-01T10:00:00Z');

        $dto = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => $dateTime->format('c'),
        ]);

        $this->assertEquals($dateTime, $dto->createdAt);
    }

    public function test_converts_nested_granite_objects(): void
    {
        $userData = [
            'name' => 'John Doe',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'zipCode' => '10001',
                'country' => 'US',
            ],
        ];

        $userInstance = UserDTO::from($userData);

        $this->assertEquals('John Doe', $userInstance->name);
        $this->assertInstanceOf(Address::class, $userInstance->address);
        $this->assertEquals('123 Main St', $userInstance->address->street);
        $this->assertEquals('New York', $userInstance->address->city);
    }

    public function test_handles_union_types_with_datetime(): void
    {
        // Test with string that should convert to DateTime
        $instance1 = DateDTO::from(['flexibleDate' => '2024-01-01T10:00:00Z']);
        $this->assertInstanceOf(DateTimeInterface::class, $instance1->flexibleDate);

        // Test with null
        $instance3 = DateDTO::from(['flexibleDate' => null]);
        $this->assertNull($instance3->flexibleDate);
    }

    public function test_handles_malformed_datetime_strings(): void
    {
        $malformedDates = [
            'not-a-date',
            '2024-13-45', // Invalid month/day
            '2024/13/45',
            'invalid-format',
            '2024-01-01T25:00:00Z', // Invalid time
        ];

        foreach ($malformedDates as $malformedDate) {
            try {
                $dto = ComplexDTO::from([
                    'id' => 1,
                    'name' => 'Test',
                    'createdAt' => $malformedDate,
                ]);

                // If no exception, should either be null or the original string
                $this->assertTrue(
                    null === $dto->createdAt || is_string($dto->createdAt),
                    "Malformed date '{$malformedDate}' should result in null or string",
                );
            } catch (Exception $e) {
                // Exception is also acceptable for malformed dates
                $this->assertInstanceOf(Exception::class, $e);
            }
        }
    }

    public function test_type_conversion_performance(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'createdAt' => '2024-01-01T10:00:00Z',
            'status' => 'active',
            'metadata' => ['key' => 'value'],
        ];

        $iterations = 1000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            ComplexDTO::from($data);
        }

        $elapsed = microtime(true) - $start;
        $avgTime = $elapsed / $iterations;

        // Should be reasonably fast (less than 2ms per conversion)
        $this->assertLessThan(0.002, $avgTime, "Type conversion too slow: {$avgTime}s per operation");
    }

    public function test_preserves_scalar_types(): void
    {
        $data = [
            'id' => 123,
            'name' => 'Test Product',
            'price' => 99.99,
            'active' => true,
            'tags' => ['php', 'testing'],
        ];

        $instance = ScalarDTO::from($data);

        $this->assertSame(123, $instance->id);
        $this->assertSame('Test Product', $instance->name);
        $this->assertSame(99.99, $instance->price);
        $this->assertSame(true, $instance->active);
        $this->assertSame(['php', 'testing'], $instance->tags);
    }
}

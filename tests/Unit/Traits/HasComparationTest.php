<?php

namespace Tests\Unit\Traits;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Ninja\Granite\Exceptions\ComparisonException;
use Tests\Fixtures\DTOs\ComplexDTO;
use Tests\Fixtures\DTOs\NestedDTO;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Helpers\TestCase;

final class HasComparationTest extends TestCase
{
    public function test_equals_returns_true_for_identical_objects(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        $dto2 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

        $this->assertTrue($dto1->equals($dto2));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        $dto2 = SimpleDTO::from(['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_returns_false_for_different_types(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        $dto2 = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_handles_null_values(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => null]);
        $dto2 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => null]);

        $this->assertTrue($dto1->equals($dto2));
    }

    public function test_equals_returns_false_when_one_null_one_not(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => null]);
        $dto2 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_compares_nested_granite_objects(): void
    {
        $author1 = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $author2 = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author1,
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author2,
        ]);

        $this->assertTrue($dto1->equals($dto2));
    }

    public function test_equals_detects_different_nested_objects(): void
    {
        $author1 = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $author2 = UserDTO::from(['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']);

        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author1,
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author2,
        ]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_compares_arrays_recursively(): void
    {
        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'laravel', 'granite'],
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'laravel', 'granite'],
        ]);

        $this->assertTrue($dto1->equals($dto2));
    }

    public function test_equals_detects_different_array_values(): void
    {
        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'laravel'],
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'symfony'],
        ]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_detects_different_array_lengths(): void
    {
        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'laravel', 'granite'],
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'laravel'],
        ]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_compares_datetime_objects_with_timezone(): void
    {
        $date1 = new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $date2 = new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'));

        $dto1 = ComplexDTO::from([
            'id' => 1,
            'createdAt' => $date1,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'createdAt' => $date2,
        ]);

        $this->assertTrue($dto1->equals($dto2));
    }

    public function test_equals_detects_different_timezones(): void
    {
        $date1 = new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $date2 = new DateTime('2024-01-15 10:00:00', new DateTimeZone('Europe/Madrid'));

        $dto1 = ComplexDTO::from([
            'id' => 1,
            'createdAt' => $date1,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'createdAt' => $date2,
        ]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_equals_compares_backed_enums(): void
    {
        $dto1 = ComplexDTO::from([
            'id' => 1,
            'name' => 'John',
            'status' => UserStatus::ACTIVE,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'name' => 'John',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertTrue($dto1->equals($dto2));
    }

    public function test_equals_detects_different_enum_values(): void
    {
        $dto1 = ComplexDTO::from([
            'id' => 1,
            'name' => 'John',
            'status' => UserStatus::ACTIVE,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'name' => 'John',
            'status' => UserStatus::INACTIVE,
        ]);

        $this->assertFalse($dto1->equals($dto2));
    }

    public function test_differs_returns_empty_array_for_equal_objects(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        $dto2 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

        $differences = $dto1->differs($dto2);

        $this->assertEmpty($differences);
    }

    public function test_differs_returns_changed_properties(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        $dto2 = SimpleDTO::from(['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'age' => 25]);

        $differences = $dto1->differs($dto2);

        $this->assertArrayHasKey('name', $differences);
        $this->assertEquals('John', $differences['name']['current']);
        $this->assertEquals('Jane', $differences['name']['new']);

        $this->assertArrayHasKey('age', $differences);
        $this->assertEquals(30, $differences['age']['current']);
        $this->assertEquals(25, $differences['age']['new']);
    }

    public function test_differs_shows_nested_differences(): void
    {
        $author1 = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $author2 = UserDTO::from(['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com']);

        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author1,
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author2,
        ]);

        $differences = $dto1->differs($dto2);

        $this->assertArrayHasKey('author', $differences);
        $this->assertArrayHasKey('name', $differences['author']);
        $this->assertEquals('John', $differences['author']['name']['current']);
        $this->assertEquals('Jane', $differences['author']['name']['new']);
    }

    public function test_differs_throws_exception_for_type_mismatch(): void
    {
        $dto1 = SimpleDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        $dto2 = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

        $this->expectException(ComparisonException::class);
        $this->expectExceptionMessage('Cannot compare objects of different types');

        $dto1->differs($dto2);
    }

    public function test_differs_formats_datetime_with_timezone(): void
    {
        $date1 = new DateTime('2024-01-15 10:30:45.123456', new DateTimeZone('UTC'));
        $date2 = new DateTime('2024-01-16 14:20:30.654321', new DateTimeZone('Europe/Madrid'));

        $dto1 = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => $date1,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'name' => 'Test',
            'createdAt' => $date2,
        ]);

        $differences = $dto1->differs($dto2);

        $this->assertArrayHasKey('createdAt', $differences);
        $this->assertStringContainsString('2024-01-15', $differences['createdAt']['current']);
        $this->assertStringContainsString('+00:00', $differences['createdAt']['current']);
        $this->assertStringContainsString('2024-01-16', $differences['createdAt']['new']);
    }

    public function test_differs_formats_enum_values(): void
    {
        $dto1 = ComplexDTO::from([
            'id' => 1,
            'name' => 'John',
            'status' => UserStatus::ACTIVE,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'name' => 'John',
            'status' => UserStatus::INACTIVE,
        ]);

        $differences = $dto1->differs($dto2);

        $this->assertArrayHasKey('status', $differences);
        $this->assertEquals('active', $differences['status']['current']);
        $this->assertEquals('inactive', $differences['status']['new']);
    }

    public function test_differs_converts_nested_granite_to_array(): void
    {
        $author = UserDTO::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => $author,
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Parent',
            'author' => null,
        ]);

        $differences = $dto1->differs($dto2);

        $this->assertArrayHasKey('author', $differences);
        $this->assertIsArray($differences['author']['current']);
        $this->assertArrayHasKey('name', $differences['author']['current']);
        $this->assertNull($differences['author']['new']);
    }

    public function test_differs_handles_array_differences(): void
    {
        $dto1 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'laravel'],
        ]);

        $dto2 = NestedDTO::from([
            'id' => 1,
            'title' => 'Post',
            'tags' => ['php', 'symfony'],
        ]);

        $differences = $dto1->differs($dto2);

        $this->assertArrayHasKey('tags', $differences);
        $this->assertEquals(['php', 'laravel'], $differences['tags']['current']);
        $this->assertEquals(['php', 'symfony'], $differences['tags']['new']);
    }

    public function test_equals_with_datetime_immutable(): void
    {
        $date1 = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));
        $date2 = new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC'));

        $dto1 = ComplexDTO::from([
            'id' => 1,
            'createdAt' => $date1,
        ]);

        $dto2 = ComplexDTO::from([
            'id' => 1,
            'createdAt' => $date2,
        ]);

        $this->assertTrue($dto1->equals($dto2));
    }
}

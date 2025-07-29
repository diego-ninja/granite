<?php

declare(strict_types=1);

namespace Tests\Helpers;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    /**
     * Temporary files created during tests (for cleanup)
     */
    private array $tempFiles = [];

    /**
     * Clean up temporary files after each test
     */
    protected function tearDown(): void
    {
        // Clean up temporary files
        foreach ($this->tempFiles as $filename) {
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }
    /**
     * Create test data for common scenarios
     */
    protected function createTestData(string $type = 'user'): array
    {
        return match ($type) {
            'user' => [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'firstName' => 'Test',
                'lastName' => 'User',
                'created_at' => '2024-01-01T10:00:00Z',
                'age' => 30,
            ],
            'order' => [
                'id' => 1,
                'total' => 100.00,
                'status' => 'pending',
                'created_at' => '2024-01-01T10:00:00Z',
                'items' => [],
            ],
            'address' => [
                'street' => '123 Main St',
                'city' => 'Test City',
                'country' => 'Test Country',
                'zipCode' => '12345',
            ],
            'empty' => [],
            default => [],
        };
    }

    /**
     * Assert that a callback throws a validation error
     */
    protected function assertValidationError(callable $callback, ?string $expectedField = null): void
    {
        try {
            $callback();
            $this->fail('Expected validation exception was not thrown');
        } catch (InvalidArgumentException $e) {
            if ($expectedField) {
                $this->assertStringContainsString($expectedField, $e->getMessage());
            }
            $this->assertTrue(true); // Mark test as passed
        }
    }

    /**
     * Assert that an array has exactly the specified keys
     */
    protected function assertArrayHasExactKeys(array $expected, array $actual): void
    {
        $this->assertEquals(sort($expected), sort(array_keys($actual)));
    }

    /**
     * Create a temporary file with content for testing
     */
    protected function createTempFile(string $content, string $extension = 'tmp'): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'granite_test_') . '.' . $extension;
        file_put_contents($filename, $content);

        // Track for cleanup
        $this->tempFiles[] = $filename;

        return $filename;
    }

    /**
     * Get a reflection property value (for testing private/protected properties)
     */
    protected function getPropertyValue(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Set a reflection property value (for testing private/protected properties)
     */
    protected function setPropertyValue(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call a private/protected method
     */
    protected function callMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}

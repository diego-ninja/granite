<?php

// tests/Unit/Exceptions/GraniteExceptionTest.php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use Exception;
use Ninja\Granite\Exceptions\GraniteException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(GraniteException::class)] class GraniteExceptionTest extends TestCase
{
    public function test_creates_exception_with_basic_parameters(): void
    {
        $exception = new GraniteException('Test message', 100);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(100, $exception->getCode());
        $this->assertEmpty($exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    public function test_creates_exception_with_context(): void
    {
        $context = ['key' => 'value', 'error' => 'test', 'data' => ['nested' => 'info']];
        $exception = new GraniteException('Test message', 100, null, $context);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(100, $exception->getCode());
        $this->assertEquals($context, $exception->getContext());
    }

    public function test_creates_exception_with_previous_exception(): void
    {
        $previous = new Exception('Previous exception');
        $exception = new GraniteException('Test message', 100, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_creates_exception_with_all_parameters(): void
    {
        $previous = new Exception('Previous exception');
        $context = ['key' => 'value'];

        $exception = new GraniteException('Test message', 100, $previous, $context);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($context, $exception->getContext());
    }

    public function test_adds_context_to_existing_exception(): void
    {
        $initialContext = ['initial' => 'value', 'type' => 'test'];
        $exception = new GraniteException('Test', 0, null, $initialContext);

        $newContext = ['additional' => 'data', 'timestamp' => '2024-01-01'];
        $result = $exception->withContext($newContext);

        $this->assertSame($exception, $result);
        $this->assertEquals([
            'initial' => 'value',
            'type' => 'test',
            'additional' => 'data',
            'timestamp' => '2024-01-01',
        ], $exception->getContext());
    }

    public function test_merges_context_overwrites_existing_keys(): void
    {
        $initialContext = ['key1' => 'original', 'key2' => 'unchanged'];
        $exception = new GraniteException('Test', 0, null, $initialContext);

        $newContext = ['key1' => 'overwritten', 'key3' => 'new'];
        $exception->withContext($newContext);

        $this->assertEquals([
            'key1' => 'overwritten',  // Overwritten
            'key2' => 'unchanged',    // Unchanged
            'key3' => 'new',          // Added
        ], $exception->getContext());
    }

    public function test_context_with_empty_array(): void
    {
        $exception = new GraniteException('Test', 0, null, ['existing' => 'value']);

        $result = $exception->withContext([]);

        $this->assertSame($exception, $result);
        $this->assertEquals(['existing' => 'value'], $exception->getContext());
    }

    public function test_multiple_context_additions(): void
    {
        $exception = new GraniteException('Test');

        $exception->withContext(['first' => 'value'])
            ->withContext(['second' => 'value'])
            ->withContext(['third' => 'value']);

        $this->assertEquals([
            'first' => 'value',
            'second' => 'value',
            'third' => 'value',
        ], $exception->getContext());
    }

    public function test_context_supports_complex_data_types(): void
    {
        $complexContext = [
            'string' => 'value',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'null' => null,
            'array' => ['nested', 'array'],
            'object' => (object) ['property' => 'value'],
        ];

        $exception = new GraniteException('Test', 0, null, $complexContext);

        $this->assertEquals($complexContext, $exception->getContext());
    }

    public function test_inherits_from_exception(): void
    {
        $exception = new GraniteException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $this->expectException(GraniteException::class);
        $this->expectExceptionMessage('Test exception');
        $this->expectExceptionCode(500);

        throw new GraniteException('Test exception', 500);
    }

    public function test_context_in_exception_chain(): void
    {
        $original = new Exception('Original error');
        $context = ['source' => 'test', 'operation' => 'validation'];

        try {
            throw new GraniteException('Granite error', 100, $original, $context);
        } catch (GraniteException $e) {
            $this->assertEquals($context, $e->getContext());
            $this->assertSame($original, $e->getPrevious());
            $this->assertEquals('Original error', $e->getPrevious()->getMessage());
        }
    }
}

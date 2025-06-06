<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use Ninja\Granite\Exceptions\SerializationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(SerializationException::class)]
class SerializationExceptionTest extends TestCase
{
    public function test_unsupported_type_exception(): void
    {
        $exception = SerializationException::unsupportedType('MyClass', 'myProperty', 'SomeType');

        $this->assertInstanceOf(SerializationException::class, $exception);
        // Message itself does not contain MyClass, but other details are present
        $this->assertStringContainsString('Cannot serialize property', $exception->getMessage());
        $this->assertStringContainsString('myProperty', $exception->getMessage());
        $this->assertStringContainsString('SomeType', $exception->getMessage());
        $this->assertEquals('Cannot serialize property "myProperty" of type "SomeType"', $exception->getMessage());

        $this->assertEquals('MyClass', $exception->getObjectType());
        $this->assertEquals('myProperty', $exception->getPropertyName());
        $this->assertEquals('serialization', $exception->getOperation());
    }

    public function test_deserialization_failed_exception(): void
    {
        $previousException = new \Exception('Previous error message.');
        $exception = SerializationException::deserializationFailed('MyOtherClass', 'Detailed failure information', $previousException);

        $this->assertInstanceOf(SerializationException::class, $exception);
        $this->assertStringContainsString('MyOtherClass', $exception->getMessage());
        $this->assertStringContainsString('Detailed failure information', $exception->getMessage());
        $this->assertEquals('Failed to deserialize MyOtherClass: Detailed failure information', $exception->getMessage());

        $this->assertEquals('MyOtherClass', $exception->getObjectType());
        $this->assertEquals('deserialization', $exception->getOperation());
        $this->assertNull($exception->getPropertyName()); // PropertyName is null for deserializationFailed
        $this->assertSame($previousException, $exception->getPrevious());

        // Test without previous exception
        $exceptionNoPrev = SerializationException::deserializationFailed('AnotherClass', 'More details.');
        $this->assertInstanceOf(SerializationException::class, $exceptionNoPrev);
        $this->assertStringContainsString('AnotherClass', $exceptionNoPrev->getMessage());
        $this->assertStringContainsString('More details', $exceptionNoPrev->getMessage());
        $this->assertNull($exceptionNoPrev->getPrevious());
        $this->assertEquals('AnotherClass', $exceptionNoPrev->getObjectType());
        $this->assertNull($exceptionNoPrev->getPropertyName());
        $this->assertEquals('deserialization', $exceptionNoPrev->getOperation());
    }
}

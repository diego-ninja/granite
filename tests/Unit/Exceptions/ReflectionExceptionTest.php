<?php

// tests/Unit/Exceptions/ReflectionExceptionTest.php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use Exception;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Exceptions\ReflectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(ReflectionException::class)] class ReflectionExceptionTest extends TestCase
{
    public function test_creates_exception_with_class_and_operation(): void
    {
        $exception = new ReflectionException('TestClass', 'property_access');

        $this->assertEquals('TestClass', $exception->getClassName());
        $this->assertEquals('property_access', $exception->getOperation());
        $this->assertInstanceOf(GraniteException::class, $exception);
    }

    public function test_creates_exception_with_custom_message(): void
    {
        $customMessage = 'Custom reflection error occurred';
        $exception = new ReflectionException('TestClass', 'method_call', $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertEquals('TestClass', $exception->getClassName());
        $this->assertEquals('method_call', $exception->getOperation());
    }

    public function test_creates_exception_with_default_message(): void
    {
        $exception = new ReflectionException('MyClass', 'class_loading');

        $expectedMessage = 'Reflection operation "class_loading" failed for class MyClass';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_creates_exception_with_previous_exception(): void
    {
        $previous = new Exception('Original PHP reflection error');
        $exception = new ReflectionException('TestClass', 'property_access', '', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_stores_context_information(): void
    {
        $exception = new ReflectionException('TestClass', 'property_access', 'Test message');

        $context = $exception->getContext();
        $this->assertEquals('TestClass', $context['class_name']);
        $this->assertEquals('property_access', $context['operation']);
    }

    public function test_class_not_found_factory_method(): void
    {
        $className = 'NonExistentClass';
        $exception = ReflectionException::classNotFound($className);

        $this->assertEquals($className, $exception->getClassName());
        $this->assertEquals('class_loading', $exception->getOperation());
        $this->assertEquals('Class "NonExistentClass" not found', $exception->getMessage());
    }

    public function test_property_access_failed_factory_method(): void
    {
        $className = 'TestClass';
        $propertyName = 'testProperty';
        $previous = new Exception('Property not accessible');

        $exception = ReflectionException::propertyAccessFailed($className, $propertyName, $previous);

        $this->assertEquals($className, $exception->getClassName());
        $this->assertEquals('property_access', $exception->getOperation());
        $this->assertStringContainsString($propertyName, $exception->getMessage());
        $this->assertStringContainsString($className, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_property_access_failed_without_previous(): void
    {
        $exception = ReflectionException::propertyAccessFailed('TestClass', 'testProperty');

        $this->assertEquals('TestClass', $exception->getClassName());
        $this->assertEquals('property_access', $exception->getOperation());
        $this->assertNull($exception->getPrevious());
    }

    public function test_factory_methods_set_correct_context(): void
    {
        $classNotFoundException = ReflectionException::classNotFound('TestClass');
        $propertyException = ReflectionException::propertyAccessFailed('TestClass', 'property');

        // Test class not found context
        $context1 = $classNotFoundException->getContext();
        $this->assertEquals('TestClass', $context1['class_name']);
        $this->assertEquals('class_loading', $context1['operation']);

        // Test property access context
        $context2 = $propertyException->getContext();
        $this->assertEquals('TestClass', $context2['class_name']);
        $this->assertEquals('property_access', $context2['operation']);
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Class "BadClass" not found');

        throw ReflectionException::classNotFound('BadClass');
    }

    public function test_exception_hierarchy(): void
    {
        $exception = new ReflectionException('TestClass', 'test_operation');

        $this->assertInstanceOf(GraniteException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_inherits_context_functionality(): void
    {
        $exception = new ReflectionException('TestClass', 'operation');

        $additionalContext = ['extra' => 'data'];
        $result = $exception->withContext($additionalContext);

        $this->assertSame($exception, $result);

        $context = $exception->getContext();
        $this->assertEquals('TestClass', $context['class_name']);
        $this->assertEquals('operation', $context['operation']);
        $this->assertEquals('data', $context['extra']);
    }

    public function test_supports_complex_class_names(): void
    {
        $complexClassName = 'Vendor\\Package\\SubPackage\\VeryLongClassName';
        $exception = ReflectionException::classNotFound($complexClassName);

        $this->assertEquals($complexClassName, $exception->getClassName());
        $this->assertStringContainsString($complexClassName, $exception->getMessage());
    }

    public function test_supports_various_operation_types(): void
    {
        $operations = [
            'class_loading',
            'property_access',
            'method_invocation',
            'constructor_call',
            'attribute_parsing',
        ];

        foreach ($operations as $operation) {
            $exception = new ReflectionException('TestClass', $operation);
            $this->assertEquals($operation, $exception->getOperation());
        }
    }
}

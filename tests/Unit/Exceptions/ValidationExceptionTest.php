<?php

// tests/Unit/Exceptions/ValidationExceptionTest.php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use Exception;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(ValidationException::class)] class ValidationExceptionTest extends TestCase
{
    public function test_creates_exception_with_object_type_and_errors(): void
    {
        $objectType = 'UserVO';
        $errors = [
            'name' => ['Name is required'],
            'email' => ['Email is required', 'Email format is invalid'],
        ];

        $exception = new ValidationException($objectType, $errors);

        $this->assertEquals($objectType, $exception->getObjectType());
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals('Validation failed for UserVO', $exception->getMessage());
    }

    public function test_creates_exception_with_custom_message(): void
    {
        $customMessage = 'Custom validation error';
        $errors = ['field' => ['error']];

        $exception = new ValidationException('TestClass', $errors, $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    public function test_creates_exception_with_previous_exception(): void
    {
        $previous = new Exception('Previous error');
        $errors = ['field' => ['error']];

        $exception = new ValidationException('TestClass', $errors, '', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_stores_context_information(): void
    {
        $objectType = 'UserVO';
        $errors = ['name' => ['Required field']];

        $exception = new ValidationException($objectType, $errors);

        $context = $exception->getContext();
        $this->assertEquals($objectType, $context['object_type']);
        $this->assertEquals($errors, $context['validation_errors']);
    }

    public function test_get_field_errors_returns_specific_field_errors(): void
    {
        $errors = [
            'name' => ['Name is required', 'Name too short'],
            'email' => ['Email format invalid'],
            'age' => ['Age must be positive'],
        ];

        $exception = new ValidationException('UserVO', $errors);

        $this->assertEquals(['Name is required', 'Name too short'], $exception->getFieldErrors('name'));
        $this->assertEquals(['Email format invalid'], $exception->getFieldErrors('email'));
        $this->assertEquals(['Age must be positive'], $exception->getFieldErrors('age'));
    }

    public function test_get_field_errors_returns_empty_for_nonexistent_field(): void
    {
        $errors = ['name' => ['Name is required']];
        $exception = new ValidationException('UserVO', $errors);

        $this->assertEquals([], $exception->getFieldErrors('nonexistent'));
    }

    public function test_has_field_errors_detects_field_presence(): void
    {
        $errors = [
            'name' => ['Name is required'],
            'email' => [],  // Empty errors array
            'age' => ['Age must be positive'],
        ];

        $exception = new ValidationException('UserVO', $errors);

        $this->assertTrue($exception->hasFieldErrors('name'));
        $this->assertFalse($exception->hasFieldErrors('email')); // Empty array
        $this->assertTrue($exception->hasFieldErrors('age'));
        $this->assertFalse($exception->hasFieldErrors('nonexistent'));
    }

    public function test_get_all_messages_flattens_all_errors(): void
    {
        $errors = [
            'name' => ['Name is required', 'Name too short'],
            'email' => ['Email format invalid'],
            'age' => ['Age must be positive'],
        ];

        $exception = new ValidationException('UserVO', $errors);

        $allMessages = $exception->getAllMessages();

        $this->assertCount(4, $allMessages);
        $this->assertContains('Name is required', $allMessages);
        $this->assertContains('Name too short', $allMessages);
        $this->assertContains('Email format invalid', $allMessages);
        $this->assertContains('Age must be positive', $allMessages);
    }

    public function test_get_all_messages_handles_empty_errors(): void
    {
        $exception = new ValidationException('UserVO', []);

        $this->assertEquals([], $exception->getAllMessages());
    }

    public function test_get_formatted_message_provides_readable_output(): void
    {
        $errors = [
            'name' => ['Name is required'],
            'email' => ['Email format invalid', 'Email already exists'],
        ];

        $exception = new ValidationException('UserVO', $errors);

        $formatted = $exception->getFormattedMessage();

        $this->assertStringContainsString('Validation failed for UserVO:', $formatted);
        $this->assertStringContainsString('• Name is required', $formatted);
        $this->assertStringContainsString('• Email format invalid', $formatted);
        $this->assertStringContainsString('• Email already exists', $formatted);
    }

    public function test_get_formatted_message_handles_empty_errors(): void
    {
        $exception = new ValidationException('UserVO', []);

        $formatted = $exception->getFormattedMessage();

        $this->assertEquals("Validation failed for UserVO:\n", $formatted);
    }

    public function test_inherits_from_granite_exception(): void
    {
        $exception = new ValidationException('UserVO', []);

        $this->assertInstanceOf(GraniteException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $errors = ['name' => ['Name is required']];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed for UserVO');

        throw new ValidationException('UserVO', $errors);
    }

    public function test_handles_complex_nested_error_structure(): void
    {
        $errors = [
            'user.name' => ['Name is required'],
            'user.email' => ['Email invalid'],
            'address.street' => ['Street is required'],
            'items.0.name' => ['Item name required'],
            'items.1.price' => ['Price must be positive'],
        ];

        $exception = new ValidationException('OrderVO', $errors);

        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals(['Name is required'], $exception->getFieldErrors('user.name'));
        $this->assertTrue($exception->hasFieldErrors('items.0.name'));

        $allMessages = $exception->getAllMessages();
        $this->assertCount(5, $allMessages);
    }

    public function test_context_includes_all_validation_data(): void
    {
        $objectType = 'ComplexVO';
        $errors = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3'],
        ];

        $exception = new ValidationException($objectType, $errors);

        $context = $exception->getContext();

        $this->assertArrayHasKey('object_type', $context);
        $this->assertArrayHasKey('validation_errors', $context);
        $this->assertEquals($objectType, $context['object_type']);
        $this->assertEquals($errors, $context['validation_errors']);
    }

    public function test_supports_inheritance_with_additional_context(): void
    {
        $exception = new ValidationException('UserVO', ['name' => ['Required']]);

        $additionalContext = ['request_id' => '12345', 'user_id' => 67890];
        $result = $exception->withContext($additionalContext);

        $this->assertSame($exception, $result);

        $context = $exception->getContext();
        $this->assertEquals('UserVO', $context['object_type']);
        $this->assertEquals('12345', $context['request_id']);
        $this->assertEquals(67890, $context['user_id']);
    }
}

<?php

namespace Tests\Unit\Mapping\Exceptions;

use Exception;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Helpers\TestCase;

#[CoversClass(MappingException::class)]
class MappingExceptionTest extends TestCase
{
    public function test_extends_granite_exception(): void
    {
        $exception = new MappingException(SimpleDTO::class, UserDTO::class);

        $this->assertInstanceOf(GraniteException::class, $exception);
    }

    public function test_constructor_with_minimal_parameters(): void
    {
        $exception = new MappingException(SimpleDTO::class, UserDTO::class);

        $this->assertEquals(SimpleDTO::class, $exception->getSourceType());
        $this->assertEquals(UserDTO::class, $exception->getDestinationType());
        $this->assertNull($exception->getPropertyName());
        $this->assertEquals(0, $exception->getCode());
    }

    public function test_constructor_with_custom_message(): void
    {
        $message = 'Custom mapping error';
        $exception = new MappingException(SimpleDTO::class, UserDTO::class, $message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_constructor_with_property_name(): void
    {
        $propertyName = 'email';
        $exception = new MappingException(SimpleDTO::class, UserDTO::class, '', $propertyName);

        $this->assertEquals($propertyName, $exception->getPropertyName());
    }

    public function test_constructor_with_all_parameters(): void
    {
        $message = 'Custom mapping error';
        $propertyName = 'email';
        $code = 123;
        $previous = new Exception('Previous exception');

        $exception = new MappingException(
            SimpleDTO::class,
            UserDTO::class,
            $message,
            $propertyName,
            $code,
            $previous,
        );

        $this->assertEquals(SimpleDTO::class, $exception->getSourceType());
        $this->assertEquals(UserDTO::class, $exception->getDestinationType());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($propertyName, $exception->getPropertyName());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_constructor_generates_default_message_without_property(): void
    {
        $exception = new MappingException(SimpleDTO::class, UserDTO::class);

        $expectedMessage = sprintf(
            'Mapping failed from %s to %s',
            SimpleDTO::class,
            UserDTO::class,
        );

        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_generates_default_message_with_property(): void
    {
        $propertyName = 'email';
        $exception = new MappingException(SimpleDTO::class, UserDTO::class, '', $propertyName);

        $expectedMessage = sprintf(
            'Mapping failed from %s to %s (property: %s)',
            SimpleDTO::class,
            UserDTO::class,
            $propertyName,
        );

        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_sets_context(): void
    {
        $propertyName = 'email';
        $exception = new MappingException(SimpleDTO::class, UserDTO::class, 'Test message', $propertyName);

        $context = $exception->getContext();

        $this->assertArrayHasKey('source_type', $context);
        $this->assertArrayHasKey('destination_type', $context);
        $this->assertArrayHasKey('property_name', $context);

        $this->assertEquals(SimpleDTO::class, $context['source_type']);
        $this->assertEquals(UserDTO::class, $context['destination_type']);
        $this->assertEquals($propertyName, $context['property_name']);
    }

    public function test_destination_type_not_found_static_factory(): void
    {
        $destinationType = 'NonExistentClass';
        $exception = MappingException::destinationTypeNotFound($destinationType);

        $this->assertEquals('unknown', $exception->getSourceType());
        $this->assertEquals($destinationType, $exception->getDestinationType());
        $this->assertNull($exception->getPropertyName());

        $expectedMessage = sprintf('Destination type "%s" does not exist', $destinationType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_transformation_failed_static_factory(): void
    {
        $sourceType = SimpleDTO::class;
        $destinationType = UserDTO::class;
        $propertyName = 'email';
        $reason = 'Invalid email format';
        $previous = new Exception('Validation failed');

        $exception = MappingException::transformationFailed(
            $sourceType,
            $destinationType,
            $propertyName,
            $reason,
            $previous,
        );

        $this->assertEquals($sourceType, $exception->getSourceType());
        $this->assertEquals($destinationType, $exception->getDestinationType());
        $this->assertEquals($propertyName, $exception->getPropertyName());
        $this->assertSame($previous, $exception->getPrevious());

        $expectedMessage = sprintf('Failed to transform property "%s": %s', $propertyName, $reason);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_transformation_failed_without_previous_exception(): void
    {
        $exception = MappingException::transformationFailed(
            SimpleDTO::class,
            UserDTO::class,
            'email',
            'Invalid format',
        );

        $this->assertNull($exception->getPrevious());
    }

    public function test_unsupported_source_type_with_object(): void
    {
        $source = new SimpleDTO(1, 'test', 'test@example.com');
        $exception = MappingException::unsupportedSourceType($source);

        $this->assertEquals(SimpleDTO::class, $exception->getSourceType());
        $this->assertEquals('unknown', $exception->getDestinationType());
        $this->assertNull($exception->getPropertyName());

        $expectedMessage = sprintf('Unsupported source type: %s', SimpleDTO::class);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_unsupported_source_type_with_scalar(): void
    {
        $source = 123;
        $exception = MappingException::unsupportedSourceType($source);

        $this->assertEquals('integer', $exception->getSourceType());
        $this->assertEquals('unknown', $exception->getDestinationType());

        $expectedMessage = 'Unsupported source type: integer';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_unsupported_source_type_with_string(): void
    {
        $source = 'test string';
        $exception = MappingException::unsupportedSourceType($source);

        $this->assertEquals('string', $exception->getSourceType());
        $expectedMessage = 'Unsupported source type: string';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_unsupported_source_type_with_array(): void
    {
        $source = ['key' => 'value'];
        $exception = MappingException::unsupportedSourceType($source);

        $this->assertEquals('array', $exception->getSourceType());
        $expectedMessage = 'Unsupported source type: array';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_unsupported_source_type_with_null(): void
    {
        $source = null;
        $exception = MappingException::unsupportedSourceType($source);

        $this->assertEquals('NULL', $exception->getSourceType());
        $expectedMessage = 'Unsupported source type: NULL';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function test_context_includes_all_relevant_data(): void
    {
        $sourceType = SimpleDTO::class;
        $destinationType = UserDTO::class;
        $propertyName = 'email';

        $exception = new MappingException($sourceType, $destinationType, 'Test', $propertyName);
        $context = $exception->getContext();

        $this->assertEquals($sourceType, $context['source_type']);
        $this->assertEquals($destinationType, $context['destination_type']);
        $this->assertEquals($propertyName, $context['property_name']);
    }

    public function test_context_with_null_property_name(): void
    {
        $exception = new MappingException(SimpleDTO::class, UserDTO::class);
        $context = $exception->getContext();

        $this->assertNull($context['property_name']);
    }
}

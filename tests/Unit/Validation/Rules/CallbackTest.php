<?php

// tests/Unit/Validation/Rules/CallbackTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Exception;
use Ninja\Granite\Support\StringHelper;
use Ninja\Granite\Validation\Rules\Callback;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(Callback::class)]
class CallbackTest extends TestCase
{
    public static function staticValidationMethod($value): bool
    {
        return is_string($value) && 'VALID' === mb_strtoupper($value);
    }
    public function test_validates_with_simple_closure(): void
    {
        $rule = new Callback(fn($value) => 'valid' === $value);

        $this->assertTrue($rule->validate('valid'));
        $this->assertFalse($rule->validate('invalid'));
        $this->assertFalse($rule->validate(''));
        $this->assertFalse($rule->validate(123));
    }

    public function test_validates_with_closure_using_type_checking(): void
    {
        $rule = new Callback(fn($value) => is_string($value) && mb_strlen($value) >= 3);

        $this->assertTrue($rule->validate('hello'));
        $this->assertTrue($rule->validate('abc'));
        $this->assertTrue($rule->validate('test123'));

        $this->assertFalse($rule->validate('ab'));
        $this->assertFalse($rule->validate(''));
        $this->assertFalse($rule->validate(123));
        $this->assertFalse($rule->validate([]));
    }

    public function test_validates_with_numeric_callback(): void
    {
        $rule = new Callback(fn($value) => is_numeric($value) && $value > 0 && $value <= 100);

        $this->assertTrue($rule->validate(50));
        $this->assertTrue($rule->validate(1));
        $this->assertTrue($rule->validate(100));
        $this->assertTrue($rule->validate('50'));
        $this->assertTrue($rule->validate('99.5'));

        $this->assertFalse($rule->validate(0));
        $this->assertFalse($rule->validate(101));
        $this->assertFalse($rule->validate(-1));
        $this->assertFalse($rule->validate('abc'));
        $this->assertFalse($rule->validate([]));
    }

    public function test_validates_with_array_callback(): void
    {
        $rule = new Callback(fn($value) => is_array($value) && count($value) >= 2 && count($value) <= 5);

        $this->assertTrue($rule->validate(['a', 'b']));
        $this->assertTrue($rule->validate([1, 2, 3]));
        $this->assertTrue($rule->validate(['one', 'two', 'three', 'four', 'five']));

        $this->assertFalse($rule->validate(['single']));
        $this->assertFalse($rule->validate([]));
        $this->assertFalse($rule->validate([1, 2, 3, 4, 5, 6]));
        $this->assertFalse($rule->validate('not-array'));
    }

    public function test_validates_with_complex_business_logic(): void
    {
        // Simulate a business rule: email must be from allowed domains
        $allowedDomains = ['company.com', 'partner.org'];

        $rule = new Callback(function ($value) use ($allowedDomains) {
            if ( ! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            $domain = mb_substr($value, mb_strpos($value, '@') + 1);
            return in_array($domain, $allowedDomains);
        });

        $this->assertTrue($rule->validate('user@company.com'));
        $this->assertTrue($rule->validate('admin@partner.org'));

        $this->assertFalse($rule->validate('user@external.com'));
        $this->assertFalse($rule->validate('invalid-email'));
        $this->assertFalse($rule->validate('user@'));
        $this->assertFalse($rule->validate(123));
    }

    public function test_validates_with_static_method_callback(): void
    {
        $rule = new Callback([self::class, 'staticValidationMethod']);

        $this->assertTrue($rule->validate('VALID'));
        $this->assertFalse($rule->validate('invalid'));
        $this->assertFalse($rule->validate(123));
    }

    public function test_validates_with_object_method_callback(): void
    {
        $validator = new CustomValidator();
        $rule = new Callback([$validator, 'validateValue']);

        $this->assertTrue($rule->validate('custom-valid'));
        $this->assertFalse($rule->validate('invalid'));
        $this->assertFalse($rule->validate(123));
    }

    public function test_validates_with_invokable_object(): void
    {
        $validator = new InvokableValidator();
        $rule = new Callback($validator);

        $this->assertTrue($rule->validate(42));
        $this->assertFalse($rule->validate(13));
        $this->assertFalse($rule->validate('42'));
    }

    public function test_validates_null_as_valid(): void
    {
        $rule = new Callback(fn($value) => 'test' === $value);
        $this->assertTrue($rule->validate(null));
    }

    public function test_callback_receives_value_parameter(): void
    {
        $receivedValue = null;

        $rule = new Callback(function ($value) use (&$receivedValue) {
            $receivedValue = $value;
            return true;
        });

        $testValue = 'test-value';
        $rule->validate($testValue);

        $this->assertEquals($testValue, $receivedValue);
    }

    public function test_callback_can_return_false_explicitly(): void
    {
        $rule = new Callback(fn($value) => false);

        $this->assertFalse($rule->validate('anything'));
        $this->assertFalse($rule->validate(123));
        $this->assertFalse($rule->validate(true));
        $this->assertFalse($rule->validate([]));
    }

    public function test_callback_can_return_true_explicitly(): void
    {
        $rule = new Callback(fn($value) => true);

        $this->assertTrue($rule->validate('anything'));
        $this->assertTrue($rule->validate(123));
        $this->assertTrue($rule->validate(false));
        $this->assertTrue($rule->validate([]));
    }

    public function test_callback_with_truthy_falsy_values(): void
    {
        // Callback that returns truthy/falsy values (not strict boolean)
        $rule = new Callback(function ($value) {
            if ('empty' === $value) {
                return '';
            }
            if ('zero' === $value) {
                return 0;
            }
            if ('one' === $value) {
                return 1;
            }
            if ('string' === $value) {
                return 'non-empty';
            }
            return false;
        });

        // Truthy values should pass
        $this->assertTrue($rule->validate('one'));
        $this->assertTrue($rule->validate('string'));

        // Falsy values should fail
        $this->assertFalse($rule->validate('empty'));
        $this->assertFalse($rule->validate('zero'));
        $this->assertFalse($rule->validate('other'));
    }

    public function test_returns_default_message(): void
    {
        $rule = new Callback(fn($value) => true);
        $message = $rule->message('field');

        $this->assertEquals('field is invalid', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $rule = new Callback(fn($value) => true);

        $this->assertEquals('username is invalid', $rule->message('username'));
        $this->assertEquals('email is invalid', $rule->message('email'));
        $this->assertEquals('custom_field is invalid', $rule->message('custom_field'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new Callback(fn($value) => true);
        $customMessage = 'This field does not meet our custom requirements';
        $rule->withMessage($customMessage);

        $message = $rule->message('field');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $rule = new Callback(fn($value) => true);
        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }

    public function test_callback_with_exception_handling(): void
    {
        // Callback that might throw an exception should be handled gracefully
        $rule = new Callback(function ($value) {
            if ('throw' === $value) {
                throw new Exception('Test exception');
            }
            return 'valid' === $value;
        });

        $this->assertTrue($rule->validate('valid'));
        $this->assertFalse($rule->validate('invalid'));

        // This might depend on implementation - it could either return false or let exception bubble up
        try {
            $result = $rule->validate('throw');
            // If no exception is thrown, the result should be false
            $this->assertFalse($result);
        } catch (Exception $e) {
            // If exception bubbles up, that's also acceptable behavior
            $this->assertEquals('Test exception', $e->getMessage());
        }
    }

    public function test_performance_with_complex_callback(): void
    {
        // Test performance with a more complex callback
        $rule = new Callback(function ($value) {
            if ( ! is_string($value)) {
                return false;
            }

            // Simulate some processing
            $processed = mb_strtolower(StringHelper::mbTrim($value));
            $words = explode(' ', $processed);

            return count($words) >= 2 && count($words) <= 10 &&
                array_reduce($words, fn($carry, $word) => $carry && mb_strlen($word) >= 2, true);
        });

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $rule->validate('valid test string');
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 validations in reasonable time (less than 50ms)
        $this->assertLessThan(0.05, $elapsed, "Callback validation took too long: {$elapsed}s");
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $rule = new Callback(fn($value) => true);
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $rule = new Callback(fn($value) => true);
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_callback_with_multiple_conditions(): void
    {
        $rule = new Callback(fn($value) => is_string($value) &&
                mb_strlen($value) >= 5 &&
                mb_strlen($value) <= 20 &&
                preg_match('/^[a-zA-Z0-9_]+$/', $value) &&
                ! is_numeric($value[0]));

        $this->assertTrue($rule->validate('valid_username'));
        $this->assertTrue($rule->validate('user123'));
        $this->assertTrue($rule->validate('another_valid_name'));

        $this->assertFalse($rule->validate('user'));           // Too short
        $this->assertFalse($rule->validate('123user'));        // Starts with number
        $this->assertFalse($rule->validate('user@name'));      // Invalid character
        $this->assertFalse($rule->validate('very_long_username_that_exceeds_limit')); // Too long
        $this->assertFalse($rule->validate(123));              // Not string
    }
}

// Helper classes for testing
class CustomValidator
{
    public function validateValue($value): bool
    {
        return is_string($value) && str_starts_with($value, 'custom-');
    }
}

class InvokableValidator
{
    public function __invoke($value): bool
    {
        return is_int($value) && 42 === $value;
    }
}

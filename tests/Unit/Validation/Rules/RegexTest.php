<?php
// tests/Unit/Validation/Rules/RegexTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Regex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\TestCase;

#[CoversClass(Regex::class)]
class RegexTest extends TestCase
{
    public function test_validates_simple_pattern_match(): void
    {
        $rule = new Regex('/^[a-z]+$/');

        $this->assertTrue($rule->validate('hello'));
        $this->assertTrue($rule->validate('world'));
        $this->assertTrue($rule->validate('test'));

        $this->assertFalse($rule->validate('Hello')); // Contains uppercase
        $this->assertFalse($rule->validate('hello123')); // Contains numbers
        $this->assertFalse($rule->validate('hello world')); // Contains space
    }

    public function test_validates_digit_pattern(): void
    {
        $rule = new Regex('/^\d+$/');

        $this->assertTrue($rule->validate('123'));
        $this->assertTrue($rule->validate('0'));
        $this->assertTrue($rule->validate('999999'));

        $this->assertFalse($rule->validate('abc'));
        $this->assertFalse($rule->validate('12a'));
        $this->assertFalse($rule->validate(''));
        $this->assertFalse($rule->validate(' 123'));
    }

    public function test_validates_email_like_pattern(): void
    {
        $rule = new Regex('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        $this->assertTrue($rule->validate('test@example.com'));
        $this->assertTrue($rule->validate('user.name@domain.org'));
        $this->assertTrue($rule->validate('test123@sub.domain.co.uk'));

        $this->assertFalse($rule->validate('invalid-email'));
        $this->assertFalse($rule->validate('@domain.com'));
        $this->assertFalse($rule->validate('test@'));
        $this->assertFalse($rule->validate('test@domain'));
    }

    public function test_validates_phone_number_pattern(): void
    {
        // Pattern for US phone format: (XXX) XXX-XXXX
        $rule = new Regex('/^\(\d{3}\) \d{3}-\d{4}$/');

        $this->assertTrue($rule->validate('(123) 456-7890'));
        $this->assertTrue($rule->validate('(999) 555-1234'));

        $this->assertFalse($rule->validate('123-456-7890'));
        $this->assertFalse($rule->validate('(123) 456-789'));
        $this->assertFalse($rule->validate('(12) 456-7890'));
        $this->assertFalse($rule->validate('(123)456-7890')); // Missing space
    }

    public function test_validates_alphanumeric_pattern(): void
    {
        $rule = new Regex('/^[a-zA-Z0-9]+$/');

        $this->assertTrue($rule->validate('abc123'));
        $this->assertTrue($rule->validate('ABC'));
        $this->assertTrue($rule->validate('123'));
        $this->assertTrue($rule->validate('Test123'));

        $this->assertFalse($rule->validate('test-123'));
        $this->assertFalse($rule->validate('test 123'));
        $this->assertFalse($rule->validate('test@123'));
        $this->assertFalse($rule->validate(''));
    }

    public function test_validates_optional_pattern(): void
    {
        // Pattern that allows empty string or 3+ alphanumeric characters
        $rule = new Regex('/^([a-zA-Z0-9]{3,})?$/');

        $this->assertTrue($rule->validate(''));
        $this->assertTrue($rule->validate('abc'));
        $this->assertTrue($rule->validate('test123'));
        $this->assertTrue($rule->validate('ABCDEF'));

        $this->assertFalse($rule->validate('ab')); // Too short
        $this->assertFalse($rule->validate('a1')); // Too short
        $this->assertFalse($rule->validate('test-123')); // Invalid character
    }

    public function test_validates_with_case_insensitive_flag(): void
    {
        $rule = new Regex('/^[a-z]+$/i');

        $this->assertTrue($rule->validate('hello'));
        $this->assertTrue($rule->validate('HELLO'));
        $this->assertTrue($rule->validate('Hello'));
        $this->assertTrue($rule->validate('hELLo'));

        $this->assertFalse($rule->validate('hello123'));
        $this->assertFalse($rule->validate('hello world'));
    }

    public function test_validates_multiline_pattern(): void
    {
        $rule = new Regex('/^line1\nline2$/');

        $this->assertTrue($rule->validate("line1\nline2"));

        $this->assertFalse($rule->validate('line1line2'));
        $this->assertFalse($rule->validate('line1 line2'));
        $this->assertFalse($rule->validate("line1\nline3"));
    }

    public function test_validates_complex_pattern(): void
    {
        // Pattern for strong password: 8+ chars, uppercase, lowercase, digit, special char
        $rule = new Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/');

        $this->assertTrue($rule->validate('Password123!'));
        $this->assertTrue($rule->validate('MyP@ssw0rd'));
        $this->assertTrue($rule->validate('Complex123$'));

        $this->assertFalse($rule->validate('password')); // No uppercase, digit, special
        $this->assertFalse($rule->validate('PASSWORD123!')); // No lowercase
        $this->assertFalse($rule->validate('Password!')); // No digit
        $this->assertFalse($rule->validate('Password123')); // No special char
        $this->assertFalse($rule->validate('Pass1!')); // Too short
    }

    public function test_validates_null_as_valid(): void
    {
        $rule = new Regex('/^test$/');
        $this->assertTrue($rule->validate(null));
    }

    public function test_rejects_non_string_values(): void
    {
        $rule = new Regex('/^test$/');

        $this->assertFalse($rule->validate(123));
        $this->assertFalse($rule->validate(true));
        $this->assertFalse($rule->validate([]));
        $this->assertFalse($rule->validate(new \stdClass()));
        $this->assertFalse($rule->validate(3.14));
    }

    public function test_handles_special_regex_characters(): void
    {
        // Pattern that includes regex special characters
        $rule = new Regex('/^test\.\*\+\?\[\]\(\)\{\}\|\$\^$/');

        $this->assertTrue($rule->validate('test.*+?[](){}|$^'));
        $this->assertFalse($rule->validate('test'));
        $this->assertFalse($rule->validate('test.'));
    }

    public function test_validates_unicode_pattern(): void
    {
        $rule = new Regex('/^[\p{L}\p{N}]+$/u');

        $this->assertTrue($rule->validate('hello'));
        $this->assertTrue($rule->validate('测试'));
        $this->assertTrue($rule->validate('тест'));
        $this->assertTrue($rule->validate('test123'));
        $this->assertTrue($rule->validate('测试123'));

        $this->assertFalse($rule->validate('test@123'));
        $this->assertFalse($rule->validate('test 123'));
    }

    public function test_ignores_all_data_parameter(): void
    {
        $rule = new Regex('/^[a-z]+$/');
        $allData = ['other_field' => 'value', 'pattern' => 'test'];

        $this->assertTrue($rule->validate('hello', $allData));
        $this->assertFalse($rule->validate('HELLO', $allData));
    }

    public function test_returns_default_message(): void
    {
        $pattern = '/^[a-zA-Z]+$/';
        $rule = new Regex($pattern);
        $message = $rule->message('username');

        $this->assertEquals("username must match the pattern $pattern", $message);
    }

    public function test_returns_default_message_with_complex_pattern(): void
    {
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$/';
        $rule = new Regex($pattern);
        $message = $rule->message('password');

        $this->assertEquals("password must match the pattern $pattern", $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new Regex('/^[a-zA-Z]+$/');
        $customMessage = 'Field must contain only letters';
        $rule->withMessage($customMessage);

        $message = $rule->message('username');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $rule = new Regex('/^test$/');
        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }

    #[DataProvider('commonPatternProvider')]
    public function test_validates_common_patterns(string $pattern, string $validValue, string $invalidValue): void
    {
        $rule = new Regex($pattern);

        $this->assertTrue($rule->validate($validValue), "Valid value '$validValue' should pass pattern '$pattern'");
        $this->assertFalse($rule->validate($invalidValue), "Invalid value '$invalidValue' should fail pattern '$pattern'");
    }

    public static function commonPatternProvider(): array
    {
        return [
            'letters only' => ['/^[a-zA-Z]+$/', 'hello', 'hello123'],
            'digits only' => ['/^\d+$/', '123', '12a'],
            'alphanumeric' => ['/^[a-zA-Z0-9]+$/', 'test123', 'test-123'],
            'email basic' => ['/^[^@]+@[^@]+\.[^@]+$/', 'test@example.com', 'invalid-email'],
            'phone US' => ['/^\d{3}-\d{3}-\d{4}$/', '123-456-7890', '123-45-6789'],
            'hex color' => ['/^#[0-9a-fA-F]{6}$/', '#FF5733', '#GG5733'],
            'postal code' => ['/^\d{5}(-\d{4})?$/', '12345', '1234'],
            'slug' => ['/^[a-z0-9-]+$/', 'my-blog-post', 'My Blog Post'],
            'version' => ['/^\d+\.\d+\.\d+$/', '1.2.3', '1.2'],
            'url path' => ['/^\/[a-zA-Z0-9\/_-]*$/', '/api/users/123', 'api/users'],
        ];
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $rule = new Regex('/^test$/');
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $rule = new Regex('/^test$/');
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_handles_empty_string(): void
    {
        // Pattern that requires at least one character
        $rule = new Regex('/^.+$/');
        $this->assertFalse($rule->validate(''));

        // Pattern that allows empty string
        $rule2 = new Regex('/^.*$/');
        $this->assertTrue($rule2->validate(''));
    }

    public function test_handles_whitespace_patterns(): void
    {
        // Pattern that requires no whitespace
        $rule = new Regex('/^\S+$/');
        $this->assertTrue($rule->validate('nowhitespace'));
        $this->assertFalse($rule->validate('has whitespace'));
        $this->assertFalse($rule->validate(' leadingspace'));
        $this->assertFalse($rule->validate('trailingspace '));

        // Pattern that allows only whitespace
        $rule2 = new Regex('/^\s+$/');
        $this->assertTrue($rule2->validate('   '));
        $this->assertTrue($rule2->validate("\t\n"));
        $this->assertFalse($rule2->validate('text'));
    }

    public function test_performance_with_complex_patterns(): void
    {
        // Test that complex regex patterns don't cause performance issues
        $complexPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,20}$/';
        $rule = new Regex($complexPattern);

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $rule->validate('TestPassword123!');
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 validations in reasonable time (less than 100ms)
        $this->assertLessThan(0.1, $elapsed, "Regex validation took too long: {$elapsed}s");
    }
}
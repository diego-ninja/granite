<?php
// tests/Unit/Validation/Rules/EachTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Each;
use Ninja\Granite\Validation\Rules\StringType;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\Email;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(Each::class)] class EachTest extends TestCase
{
    public function test_validates_array_with_single_rule(): void
    {
        $rule = new Each(new StringType());

        $this->assertTrue($rule->validate(['test', 'another', 'string']));
        $this->assertTrue($rule->validate([''])); // Empty string is still a string
        $this->assertFalse($rule->validate(['test', 123])); // 123 is not a string
        $this->assertFalse($rule->validate(['test', true])); // true is not a string
    }

    public function test_validates_array_with_multiple_rules(): void
    {
        $rule = new Each([new StringType(), new Min(3)]);

        $this->assertTrue($rule->validate(['test', 'another', 'hello']));
        $this->assertFalse($rule->validate(['test', 'no'])); // 'no' fails Min(3)
        $this->assertFalse($rule->validate(['test', 123])); // 123 fails StringType
        $this->assertFalse($rule->validate([123, 'test'])); // 123 fails StringType
    }

    public function test_passes_validation_for_empty_array(): void
    {
        $rule = new Each(new StringType());

        $this->assertTrue($rule->validate([]));
    }

    public function test_passes_validation_for_non_array(): void
    {
        $rule = new Each(new StringType());

        $this->assertTrue($rule->validate('not-an-array'));
        $this->assertTrue($rule->validate(123));
        $this->assertTrue($rule->validate(null));
        $this->assertTrue($rule->validate(true));
    }

    public function test_returns_specific_error_message_with_index_for_single_rule(): void
    {
        $rule = new Each(new StringType());
        $rule->validate(['test', 123, 'another']); // Should fail at index 1

        $message = $rule->message('items');
        $this->assertStringContainsString('index 1', $message);
        $this->assertStringContainsString('items', $message);
    }

    public function test_returns_specific_error_message_with_index_for_multiple_rules(): void
    {
        $rule = new Each([new StringType(), new Min(5)]);
        $result = $rule->validate(['hello', 'hi']); // 'hi' should fail Min(5)
        $this->assertFalse($result);

        $message = $rule->message('tags');

        // For multiple rules, it should show the specific rule's message
        $this->assertStringContainsString('tags[1]', $message);
        $this->assertStringContainsString('must be at least 5', $message);
    }

    public function test_multiple_rules_first_rule_failure_shows_specific_message(): void
    {
        $rule = new Each([new StringType(), new Min(5)]);
        $result = $rule->validate(['hello', 123]); // 123 should fail StringType (first rule)
        $this->assertFalse($result);

        $message = $rule->message('tags');

        // Should show StringType error message with proper formatting
        $this->assertStringContainsString('tags[1]', $message);
        $this->assertStringContainsString('must be a string', $message);
    }

    public function test_stops_at_first_failure(): void
    {
        $rule = new Each(new StringType());
        $rule->validate(['valid', 123, 456]); // Should stop at index 1

        $message = $rule->message('items');

        // Should only mention the first failure (index 1), not subsequent ones
        $this->assertStringContainsString('index 1', $message);
        $this->assertStringNotContainsString('index 2', $message);
        $this->assertEquals('Item at index 1 in items is invalid', $message);
    }

    public function test_validates_emails_in_array(): void
    {
        $rule = new Each(new Email());

        $validEmails = ['test@example.com', 'user@domain.org'];
        $invalidEmails = ['test@example.com', 'not-an-email'];

        $this->assertTrue($rule->validate($validEmails));
        $this->assertFalse($rule->validate($invalidEmails));
    }

    public function test_validates_with_required_rule(): void
    {
        $rule = new Each(new Required());

        $this->assertTrue($rule->validate(['value1', 'value2', 0, false]));
        $this->assertFalse($rule->validate(['value1', null, 'value3'])); // null fails Required
    }

    public function test_complex_validation_scenario(): void
    {
        // Each item must be a string with at least 2 characters
        $rule = new Each([new StringType(), new Min(2)]);

        $validArray = ['ab', 'test', 'hello world'];
        $invalidArray1 = ['ab', 'x']; // 'x' fails Min(2)
        $invalidArray2 = ['ab', 123]; // 123 fails StringType
        $invalidArray3 = ['ab', '']; // '' fails Min(2)

        $this->assertTrue($rule->validate($validArray));
        $this->assertFalse($rule->validate($invalidArray1));
        $this->assertFalse($rule->validate($invalidArray2));
        $this->assertFalse($rule->validate($invalidArray3));
    }

    public function test_returns_default_message_when_no_specific_failure(): void
    {
        $rule = new Each(new StringType());

        // Force a generic error by accessing message before validation
        $message = $rule->message('items');
        $this->assertEquals('All items in items must be valid', $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new Each(new StringType());
        $customMessage = 'All items must be valid strings';
        $rule->withMessage($customMessage);

        $message = $rule->message('items');
        $this->assertEquals($customMessage, $message);
    }

    public function test_custom_message_overrides_specific_error(): void
    {
        $rule = new Each(new StringType());
        $rule->withMessage('Custom error message');

        $rule->validate(['test', 123]); // Should fail
        $message = $rule->message('items');
        $this->assertEquals('Custom error message', $message);
    }

    public function test_handles_nested_arrays(): void
    {
        $rule = new Each(new StringType());

        // Arrays containing arrays should fail StringType validation
        $this->assertFalse($rule->validate([['nested'], 'string']));
        $this->assertFalse($rule->validate(['string', ['nested']]));
    }

    public function test_validates_associative_arrays(): void
    {
        $rule = new Each(new StringType());

        $this->assertTrue($rule->validate(['key1' => 'value1', 'key2' => 'value2']));
        $this->assertFalse($rule->validate(['key1' => 'value1', 'key2' => 123]));

        // Check that the error message includes the string key
        $message = $rule->message('items');
        $this->assertStringContainsString('index key2', $message);
    }

    public function test_validates_mixed_indexed_and_associative(): void
    {
        $rule = new Each(new StringType());

        $mixedArray = [0 => 'indexed', 'key' => 'associative', 1 => 'another'];
        $this->assertTrue($rule->validate($mixedArray));

        $mixedInvalid = [0 => 'indexed', 'key' => 123, 1 => 'another'];
        $this->assertFalse($rule->validate($mixedInvalid));

        // Check that the error message includes the string key
        $message = $rule->message('items');
        $this->assertStringContainsString('index key', $message);
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $rule = new Each(new StringType());
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $rule = new Each(new StringType());
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_multiple_rules_with_string_keys(): void
    {
        $rule = new Each([new StringType(), new Min(5)]);

        $data = ['long_key' => 'hello', 'short_key' => 'hi']; // 'hi' fails Min(5)
        $this->assertFalse($rule->validate($data));

        $message = $rule->message('items');
        $this->assertStringContainsString('items[short_key]', $message);
        $this->assertStringContainsString('must be at least 5', $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $rule = new Each(new StringType());
        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }

    public function test_handles_numeric_string_keys(): void
    {
        $rule = new Each(new StringType());

        // Array with numeric string keys
        $data = ['0' => 'valid', '1' => 123, '2' => 'valid'];
        $this->assertFalse($rule->validate($data));

        $message = $rule->message('items');
        $this->assertStringContainsString('index 1', $message);
    }

    public function test_empty_array_after_failed_validation(): void
    {
        $rule = new Each(new StringType());

        // First validation fails
        $rule->validate(['test', 123]);

        // Empty array should pass and not show previous error
        $this->assertTrue($rule->validate([]));

        // Message should be generic since no current failure
        $message = $rule->message('items');
        $this->assertEquals('All items in items must be valid', $message);
    }
}
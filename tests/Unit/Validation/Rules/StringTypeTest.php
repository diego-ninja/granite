<?php

// tests/Unit/Validation/Rules/StringTypeTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\StringType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(StringType::class)] class StringTypeTest extends TestCase
{
    private StringType $rule;

    protected function setUp(): void
    {
        $this->rule = new StringType();
        parent::setUp();
    }

    public static function validStringValuesProvider(): array
    {
        return [
            'empty string' => [''],
            'single character' => ['a'],
            'numeric string' => ['123'],
            'float string' => ['3.14'],
            'multiline string' => ["line1\nline2"],
            'unicode string' => ['こんにちは'],
            'emoji string' => ['🚀 🎯 ✅'],
            'json string' => ['{"key": "value"}'],
            'xml string' => ['<tag>content</tag>'],
            'whitespace only' => ['   '],
            'tab and newlines' => ["\t\n\r"],
        ];
    }

    public static function invalidNonStringValuesProvider(): array
    {
        return [
            'integer zero' => [0],
            'positive integer' => [42],
            'negative integer' => [-10],
            'float zero' => [0.0],
            'positive float' => [3.14],
            'negative float' => [-2.71],
            'true boolean' => [true],
            'false boolean' => [false],
            'empty array' => [[]],
            'filled array' => [['a', 'b']],
            'stdClass object' => [new stdClass()],
            'anonymous object' => [(object) ['prop' => 'value']],
        ];
    }

    public function test_validates_string_values(): void
    {
        $this->assertTrue($this->rule->validate('test'));
        $this->assertTrue($this->rule->validate(''));
        $this->assertTrue($this->rule->validate('0'));
        $this->assertTrue($this->rule->validate('123'));
        $this->assertTrue($this->rule->validate('hello world'));
        $this->assertTrue($this->rule->validate('Special chars: áéíóú ñ €'));
    }

    public function test_validates_null_as_valid(): void
    {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_rejects_integer_values(): void
    {
        $this->assertFalse($this->rule->validate(0));
        $this->assertFalse($this->rule->validate(123));
        $this->assertFalse($this->rule->validate(-456));
    }

    public function test_rejects_float_values(): void
    {
        $this->assertFalse($this->rule->validate(0.0));
        $this->assertFalse($this->rule->validate(3.14));
        $this->assertFalse($this->rule->validate(-2.71));
    }

    public function test_rejects_boolean_values(): void
    {
        $this->assertFalse($this->rule->validate(true));
        $this->assertFalse($this->rule->validate(false));
    }

    public function test_rejects_array_values(): void
    {
        $this->assertFalse($this->rule->validate([]));
        $this->assertFalse($this->rule->validate(['item']));
        $this->assertFalse($this->rule->validate([1, 2, 3]));
    }

    public function test_rejects_object_values(): void
    {
        $this->assertFalse($this->rule->validate(new stdClass()));
        $this->assertFalse($this->rule->validate((object) ['key' => 'value']));
    }

    public function test_rejects_resource_values(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertFalse($this->rule->validate($resource));
        fclose($resource);
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'number' => 123];

        $this->assertTrue($this->rule->validate('test', $allData));
        $this->assertFalse($this->rule->validate(123, $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('name');
        $this->assertEquals('name must be a string', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('email must be a string', $this->rule->message('email'));
        $this->assertEquals('description must be a string', $this->rule->message('description'));
        $this->assertEquals('user_name must be a string', $this->rule->message('user_name'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'This field must be text';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('name');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    #[DataProvider('validStringValuesProvider')] public function test_validates_various_string_types(string $value): void
    {
        $this->assertTrue($this->rule->validate($value));
    }

    #[DataProvider('invalidNonStringValuesProvider')] public function test_rejects_non_string_values(mixed $value): void
    {
        $this->assertFalse($this->rule->validate($value));
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $this->rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $this->rule);
    }

    public function test_handles_string_with_null_bytes(): void
    {
        $stringWithNull = "test\0string";
        $this->assertTrue($this->rule->validate($stringWithNull));
    }

    public function test_handles_very_long_strings(): void
    {
        $longString = str_repeat('a', 10000);
        $this->assertTrue($this->rule->validate($longString));
    }
}

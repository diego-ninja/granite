<?php

// tests/Unit/Validation/Rules/RequiredTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Required;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Required::class)] class RequiredTest extends TestCase
{
    private Required $rule;

    protected function setUp(): void
    {
        $this->rule = new Required();
        parent::setUp();
    }

    public static function validNonNullValuesProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace string' => [' '],
            'zero integer' => [0],
            'zero float' => [0.0],
            'false boolean' => [false],
            'empty array' => [[]],
            'negative number' => [-1],
            'object' => [new stdClass()],
            'resource' => [fopen('php://memory', 'r')],
        ];
    }

    public function test_validates_non_null_string_values(): void
    {
        $this->assertTrue($this->rule->validate('test'));
        $this->assertTrue($this->rule->validate(''));  // Empty string is not null
        $this->assertTrue($this->rule->validate('0')); // String zero
    }

    public function test_validates_non_null_numeric_values(): void
    {
        $this->assertTrue($this->rule->validate(0));
        $this->assertTrue($this->rule->validate(1));
        $this->assertTrue($this->rule->validate(-1));
        $this->assertTrue($this->rule->validate(0.0));
        $this->assertTrue($this->rule->validate(3.14));
    }

    public function test_validates_non_null_boolean_values(): void
    {
        $this->assertTrue($this->rule->validate(true));
        $this->assertTrue($this->rule->validate(false));
    }

    public function test_validates_non_null_array_values(): void
    {
        $this->assertTrue($this->rule->validate([]));
        $this->assertTrue($this->rule->validate(['item']));
        $this->assertTrue($this->rule->validate([0, 1, 2]));
    }

    public function test_validates_non_null_object_values(): void
    {
        $this->assertTrue($this->rule->validate(new stdClass()));
        $this->assertTrue($this->rule->validate((object) ['key' => 'value']));
    }

    public function test_fails_validation_for_null(): void
    {
        $this->assertFalse($this->rule->validate(null));
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'another' => 123];

        $this->assertTrue($this->rule->validate('test', $allData));
        $this->assertFalse($this->rule->validate(null, $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('name');
        $this->assertEquals('name is required', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('email is required', $this->rule->message('email'));
        $this->assertEquals('password is required', $this->rule->message('password'));
        $this->assertEquals('user_name is required', $this->rule->message('user_name'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'Please provide a name';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('name');
        $this->assertEquals($customMessage, $message);
    }

    public function test_custom_message_overrides_default_for_any_property(): void
    {
        $customMessage = 'This field is mandatory';
        $this->rule->withMessage($customMessage);

        $this->assertEquals($customMessage, $this->rule->message('name'));
        $this->assertEquals($customMessage, $this->rule->message('email'));
        $this->assertEquals($customMessage, $this->rule->message('any_field'));
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    public function test_supports_method_chaining(): void
    {
        $message = $this->rule->withMessage('Custom message')->message('field');

        $this->assertEquals('Custom message', $message);
    }

    #[DataProvider('validNonNullValuesProvider')] public function test_validates_various_non_null_values(mixed $value): void
    {
        $this->assertTrue($this->rule->validate($value));
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $this->rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $this->rule);
    }
}

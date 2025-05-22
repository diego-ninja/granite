<?php
// tests/Unit/Validation/Rules/BooleanTypeTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\BooleanType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\TestCase;

#[CoversClass(BooleanType::class)]
class BooleanTypeTest extends TestCase
{
    private BooleanType $rule;

    protected function setUp(): void
    {
        $this->rule = new BooleanType();
        parent::setUp();
    }

    public function test_validates_true_boolean(): void
    {
        $this->assertTrue($this->rule->validate(true));
    }

    public function test_validates_false_boolean(): void
    {
        $this->assertTrue($this->rule->validate(false));
    }

    public function test_validates_null_as_valid(): void
    {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_rejects_string_values(): void
    {
        $this->assertFalse($this->rule->validate('true'));
        $this->assertFalse($this->rule->validate('false'));
        $this->assertFalse($this->rule->validate('1'));
        $this->assertFalse($this->rule->validate('0'));
        $this->assertFalse($this->rule->validate(''));
        $this->assertFalse($this->rule->validate('yes'));
        $this->assertFalse($this->rule->validate('no'));
    }

    public function test_rejects_integer_values(): void
    {
        $this->assertFalse($this->rule->validate(1));
        $this->assertFalse($this->rule->validate(0));
        $this->assertFalse($this->rule->validate(-1));
        $this->assertFalse($this->rule->validate(123));
    }

    public function test_rejects_float_values(): void
    {
        $this->assertFalse($this->rule->validate(1.0));
        $this->assertFalse($this->rule->validate(0.0));
        $this->assertFalse($this->rule->validate(3.14));
        $this->assertFalse($this->rule->validate(-2.71));
    }

    public function test_rejects_array_values(): void
    {
        $this->assertFalse($this->rule->validate([]));
        $this->assertFalse($this->rule->validate([true]));
        $this->assertFalse($this->rule->validate([false]));
        $this->assertFalse($this->rule->validate(['boolean' => true]));
    }

    public function test_rejects_object_values(): void
    {
        $this->assertFalse($this->rule->validate(new \stdClass()));
        $this->assertFalse($this->rule->validate((object) ['value' => true]));
    }

    public function test_rejects_resource_values(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertFalse($this->rule->validate($resource));
        fclose($resource);
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'flag' => false];

        $this->assertTrue($this->rule->validate(true, $allData));
        $this->assertTrue($this->rule->validate(false, $allData));
        $this->assertFalse($this->rule->validate(1, $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('isActive');
        $this->assertEquals('isActive must be a boolean', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('flag must be a boolean', $this->rule->message('flag'));
        $this->assertEquals('enabled must be a boolean', $this->rule->message('enabled'));
        $this->assertEquals('is_published must be a boolean', $this->rule->message('is_published'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'This field must be true or false';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('isActive');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    #[DataProvider('validBooleanValuesProvider')]
    public function test_validates_boolean_values(bool $value): void
    {
        $this->assertTrue($this->rule->validate($value));
    }

    public static function validBooleanValuesProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    #[DataProvider('invalidNonBooleanValuesProvider')]
    public function test_rejects_non_boolean_values(mixed $value): void
    {
        $this->assertFalse($this->rule->validate($value));
    }

    public static function invalidNonBooleanValuesProvider(): array
    {
        return [
            'string true' => ['true'],
            'string false' => ['false'],
            'integer 1' => [1],
            'integer 0' => [0],
            'float 1.0' => [1.0],
            'float 0.0' => [0.0],
            'empty string' => [''],
            'non-empty string' => ['text'],
            'empty array' => [[]],
            'filled array' => [[true]],
            'stdClass object' => [new \stdClass()],
            'anonymous object' => [(object) ['bool' => true]],
        ];
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $this->rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $this->rule);
    }

    public function test_validates_boolean_in_conditional_expressions(): void
    {
        // Test that actual boolean values work in conditional contexts
        $trueValue = true;
        $falseValue = false;

        $this->assertTrue($this->rule->validate($trueValue));
        $this->assertTrue($this->rule->validate($falseValue));

        // Verify they work as expected in conditionals
        $this->assertTrue($trueValue === true);
        $this->assertTrue($falseValue === false);
    }

    public function test_strict_type_checking(): void
    {
        // Ensure we're doing strict type checking, not truthy/falsy
        $this->assertFalse($this->rule->validate(1));   // truthy but not boolean
        $this->assertFalse($this->rule->validate(0));   // falsy but not boolean
        $this->assertFalse($this->rule->validate(''));  // falsy but not boolean
        $this->assertFalse($this->rule->validate([]));  // falsy but not boolean
    }
}
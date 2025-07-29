<?php

// tests/Unit/Validation/Rules/NumberTypeTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\NumberType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(NumberType::class)] class NumberTypeTest extends TestCase
{
    private NumberType $rule;

    protected function setUp(): void
    {
        $this->rule = new NumberType();
        parent::setUp();
    }

    public static function validNumberValuesProvider(): array
    {
        return [
            'zero integer' => [0],
            'positive integer' => [42],
            'negative integer' => [-10],
            'large integer' => [1000000],
            'zero float' => [0.0],
            'positive float' => [3.14159],
            'negative float' => [-2.71828],
            'scientific notation positive' => [1.23e10],
            'scientific notation negative' => [4.56e-5],
            'very small decimal' => [0.000001],
            'very large decimal' => [999999.999999],
        ];
    }

    public static function invalidNonNumberValuesProvider(): array
    {
        return [
            'empty string' => [''],
            'numeric string' => ['123'],
            'float string' => ['3.14'],
            'text string' => ['hello'],
            'true boolean' => [true],
            'false boolean' => [false],
            'empty array' => [[]],
            'numeric array' => [[123]],
            'stdClass object' => [new stdClass()],
            'anonymous object' => [(object) ['value' => 123]],
        ];
    }

    public function test_validates_integer_values(): void
    {
        $this->assertTrue($this->rule->validate(0));
        $this->assertTrue($this->rule->validate(1));
        $this->assertTrue($this->rule->validate(-1));
        $this->assertTrue($this->rule->validate(123));
        $this->assertTrue($this->rule->validate(-456));
        $this->assertTrue($this->rule->validate(PHP_INT_MAX));
        $this->assertTrue($this->rule->validate(PHP_INT_MIN));
    }

    public function test_validates_float_values(): void
    {
        $this->assertTrue($this->rule->validate(0.0));
        $this->assertTrue($this->rule->validate(3.14));
        $this->assertTrue($this->rule->validate(-2.71));
        $this->assertTrue($this->rule->validate(1.23e-4));
        $this->assertTrue($this->rule->validate(1.23E+10));
        $this->assertTrue($this->rule->validate(PHP_FLOAT_MAX));
        $this->assertTrue($this->rule->validate(PHP_FLOAT_MIN));
    }

    public function test_validates_special_float_values(): void
    {
        $this->assertTrue($this->rule->validate(INF));
        $this->assertTrue($this->rule->validate(-INF));
        $this->assertTrue($this->rule->validate(NAN));
    }

    public function test_validates_null_as_valid(): void
    {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_rejects_string_values(): void
    {
        $this->assertFalse($this->rule->validate(''));
        $this->assertFalse($this->rule->validate('123'));
        $this->assertFalse($this->rule->validate('3.14'));
        $this->assertFalse($this->rule->validate('text'));
        $this->assertFalse($this->rule->validate('0'));
    }

    public function test_rejects_boolean_values(): void
    {
        $this->assertFalse($this->rule->validate(true));
        $this->assertFalse($this->rule->validate(false));
    }

    public function test_rejects_array_values(): void
    {
        $this->assertFalse($this->rule->validate([]));
        $this->assertFalse($this->rule->validate([1, 2, 3]));
        $this->assertFalse($this->rule->validate(['123']));
    }

    public function test_rejects_object_values(): void
    {
        $this->assertFalse($this->rule->validate(new stdClass()));
        $this->assertFalse($this->rule->validate((object) ['number' => 123]));
    }

    public function test_rejects_resource_values(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertFalse($this->rule->validate($resource));
        fclose($resource);
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'string' => 'text'];

        $this->assertTrue($this->rule->validate(123, $allData));
        $this->assertFalse($this->rule->validate('text', $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('price');
        $this->assertEquals('price must be a number', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('age must be a number', $this->rule->message('age'));
        $this->assertEquals('weight must be a number', $this->rule->message('weight'));
        $this->assertEquals('user_id must be a number', $this->rule->message('user_id'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'This field must be numeric';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('price');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    #[DataProvider('validNumberValuesProvider')] public function test_validates_various_number_types(int|float $value): void
    {
        $this->assertTrue($this->rule->validate($value));
    }

    #[DataProvider('invalidNonNumberValuesProvider')] public function test_rejects_non_number_values(mixed $value): void
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

    public function test_distinguishes_between_string_numbers_and_actual_numbers(): void
    {
        // These should fail (string representations)
        $this->assertFalse($this->rule->validate('0'));
        $this->assertFalse($this->rule->validate('123'));
        $this->assertFalse($this->rule->validate('3.14'));
        $this->assertFalse($this->rule->validate('-456'));

        // These should pass (actual numbers)
        $this->assertTrue($this->rule->validate(0));
        $this->assertTrue($this->rule->validate(123));
        $this->assertTrue($this->rule->validate(3.14));
        $this->assertTrue($this->rule->validate(-456));
    }
}

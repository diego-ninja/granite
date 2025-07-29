<?php

// tests/Unit/Validation/Rules/IntegerTypeTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\IntegerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(IntegerType::class)]
class IntegerTypeTest extends TestCase
{
    private IntegerType $rule;

    protected function setUp(): void
    {
        $this->rule = new IntegerType();
        parent::setUp();
    }

    public static function validIntegerValuesProvider(): array
    {
        return [
            'zero' => [0],
            'positive small' => [1],
            'positive medium' => [123],
            'positive large' => [999999],
            'negative small' => [-1],
            'negative medium' => [-123],
            'negative large' => [-999999],
            'max int' => [PHP_INT_MAX],
            'min int' => [PHP_INT_MIN],
        ];
    }

    public static function invalidNonIntegerValuesProvider(): array
    {
        return [
            'float zero' => [0.0],
            'positive float' => [3.14],
            'negative float' => [-2.71],
            'string zero' => ['0'],
            'string integer' => ['123'],
            'string float' => ['3.14'],
            'empty string' => [''],
            'text string' => ['hello'],
            'true boolean' => [true],
            'false boolean' => [false],
            'empty array' => [[]],
            'integer array' => [[123]],
            'stdClass object' => [new stdClass()],
            'anonymous object' => [(object) ['int' => 123]],
        ];
    }

    public function test_validates_positive_integers(): void
    {
        $this->assertTrue($this->rule->validate(1));
        $this->assertTrue($this->rule->validate(123));
        $this->assertTrue($this->rule->validate(999999));
        $this->assertTrue($this->rule->validate(PHP_INT_MAX));
    }

    public function test_validates_negative_integers(): void
    {
        $this->assertTrue($this->rule->validate(-1));
        $this->assertTrue($this->rule->validate(-123));
        $this->assertTrue($this->rule->validate(-999999));
        $this->assertTrue($this->rule->validate(PHP_INT_MIN));
    }

    public function test_validates_zero(): void
    {
        $this->assertTrue($this->rule->validate(0));
    }

    public function test_validates_null_as_valid(): void
    {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_rejects_float_values(): void
    {
        $this->assertFalse($this->rule->validate(1.0));
        $this->assertFalse($this->rule->validate(3.14));
        $this->assertFalse($this->rule->validate(-2.71));
        $this->assertFalse($this->rule->validate(0.0));
        $this->assertFalse($this->rule->validate(1.1));
        $this->assertFalse($this->rule->validate(PHP_FLOAT_MAX));
    }

    public function test_rejects_string_numbers(): void
    {
        $this->assertFalse($this->rule->validate('0'));
        $this->assertFalse($this->rule->validate('123'));
        $this->assertFalse($this->rule->validate('-456'));
        $this->assertFalse($this->rule->validate('3.14'));
        $this->assertFalse($this->rule->validate('1e10'));
    }

    public function test_rejects_string_values(): void
    {
        $this->assertFalse($this->rule->validate(''));
        $this->assertFalse($this->rule->validate('text'));
        $this->assertFalse($this->rule->validate('hello world'));
        $this->assertFalse($this->rule->validate(' '));
        $this->assertFalse($this->rule->validate('true'));
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
        $this->assertFalse($this->rule->validate([0]));
    }

    public function test_rejects_object_values(): void
    {
        $this->assertFalse($this->rule->validate(new stdClass()));
        $this->assertFalse($this->rule->validate((object) ['value' => 123]));
    }

    public function test_rejects_resource_values(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertFalse($this->rule->validate($resource));
        fclose($resource);
    }

    public function test_rejects_special_float_values(): void
    {
        $this->assertFalse($this->rule->validate(INF));
        $this->assertFalse($this->rule->validate(-INF));
        $this->assertFalse($this->rule->validate(NAN));
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'number' => 456];

        $this->assertTrue($this->rule->validate(123, $allData));
        $this->assertFalse($this->rule->validate('123', $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('count');
        $this->assertEquals('count must be an integer', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('id must be an integer', $this->rule->message('id'));
        $this->assertEquals('quantity must be an integer', $this->rule->message('quantity'));
        $this->assertEquals('user_id must be an integer', $this->rule->message('user_id'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'This field must be a whole number';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('count');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    #[DataProvider('validIntegerValuesProvider')]
    public function test_validates_various_integer_types(int $value): void
    {
        $this->assertTrue($this->rule->validate($value));
    }

    #[DataProvider('invalidNonIntegerValuesProvider')]
    public function test_rejects_non_integer_values(mixed $value): void
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

    public function test_distinguishes_between_int_and_float(): void
    {
        // These should pass (actual integers)
        $this->assertTrue($this->rule->validate(123));
        $this->assertTrue($this->rule->validate(-456));
        $this->assertTrue($this->rule->validate(0));

        // These should fail (floats, even if they represent whole numbers)
        $this->assertFalse($this->rule->validate(123.0));
        $this->assertFalse($this->rule->validate(-456.0));
        $this->assertFalse($this->rule->validate(0.0));
    }

    public function test_handles_large_numbers(): void
    {
        // Test with very large integers
        $this->assertTrue($this->rule->validate(PHP_INT_MAX));

        // Test with very small integers
        $this->assertTrue($this->rule->validate(PHP_INT_MIN));
    }

    public function test_strict_type_checking(): void
    {
        // Ensure strict type checking - no type coercion
        $this->assertTrue($this->rule->validate(1));     // int
        $this->assertFalse($this->rule->validate(1.0));  // float
        $this->assertFalse($this->rule->validate('1'));  // string
        $this->assertFalse($this->rule->validate(true)); // bool (which is 1 when coerced)
    }
}

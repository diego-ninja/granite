<?php

// tests/Unit/Validation/Rules/MaxTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Max;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Max::class)] class MaxTest extends TestCase
{
    public static function validMaxValuesProvider(): array
    {
        return [
            // String length tests
            [5, 'hello', true],
            [5, 'hello world', false],
            [0, '', true],
            [1, 'x', true],
            [1, 'xy', false],

            // Numeric tests
            [10, 10, true],
            [10, 9, true],
            [10, 11, false],
            [7.5, 7.5, true],
            [7.5, 7.4, true],
            [7.5, 7.6, false],

            // Array tests
            [2, ['a', 'b'], true],
            [2, ['a', 'b', 'c'], false],
            [0, [], true],
            [1, ['item'], true],
            [1, ['item1', 'item2'], false],

            // Edge cases
            [0, 0, true],
            [0, 1, false],
            [-5, -5, true],
            [-5, -4, false],
            [-5, -6, true],
        ];
    }
    public function test_validates_string_length(): void
    {
        $rule = new Max(5);

        $this->assertTrue($rule->validate('hello'));  // exactly 5
        $this->assertTrue($rule->validate('hi'));     // less than 5
        $this->assertTrue($rule->validate(''));       // less than 5
        $this->assertFalse($rule->validate('hello world')); // more than 5
        $this->assertFalse($rule->validate('123456')); // more than 5
    }

    public function test_validates_numeric_values(): void
    {
        $rule = new Max(10);

        // Integers
        $this->assertTrue($rule->validate(10));   // exactly 10
        $this->assertTrue($rule->validate(5));    // less than 10
        $this->assertTrue($rule->validate(0));    // less than 10
        $this->assertTrue($rule->validate(-5));   // less than 10
        $this->assertFalse($rule->validate(11));  // more than 10
        $this->assertFalse($rule->validate(100)); // much more than 10

        // Floats
        $this->assertTrue($rule->validate(10.0));  // exactly 10.0
        $this->assertTrue($rule->validate(9.9));   // slightly less
        $this->assertTrue($rule->validate(5.5));   // less
        $this->assertFalse($rule->validate(10.1)); // slightly more
        $this->assertFalse($rule->validate(15.7)); // much more
    }

    public function test_validates_array_count(): void
    {
        $rule = new Max(3);

        $this->assertTrue($rule->validate(['a', 'b', 'c']));   // exactly 3
        $this->assertTrue($rule->validate(['a', 'b']));        // less than 3
        $this->assertTrue($rule->validate(['a']));             // less than 3
        $this->assertTrue($rule->validate([]));                // less than 3
        $this->assertFalse($rule->validate(['a', 'b', 'c', 'd'])); // more than 3
    }

    public function test_validates_null_as_valid(): void
    {
        $rule = new Max(5);
        $this->assertTrue($rule->validate(null));
    }

    public function test_validates_unknown_types_as_valid(): void
    {
        $rule = new Max(5);

        $this->assertTrue($rule->validate(new stdClass()));
        $this->assertTrue($rule->validate(true));
        $this->assertTrue($rule->validate(false));
    }

    public function test_supports_float_maximum(): void
    {
        $rule = new Max(7.5);

        $this->assertTrue($rule->validate(7.5));  // exactly 7.5
        $this->assertTrue($rule->validate(7.4));  // less than 7.5
        $this->assertTrue($rule->validate(7));    // less than 7.5 (int)
        $this->assertFalse($rule->validate(7.6)); // more than 7.5
        $this->assertFalse($rule->validate(8));   // more than 7.5 (int)
    }

    public function test_returns_default_message(): void
    {
        $rule = new Max(100);
        $message = $rule->message('score');
        $this->assertEquals('score must be at most 100', $message);
    }

    public function test_returns_default_message_with_float(): void
    {
        $rule = new Max(99.99);
        $message = $rule->message('price');
        $this->assertEquals('price must be at most 99.99', $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new Max(120);
        $customMessage = 'Age cannot exceed 120 years';
        $rule->withMessage($customMessage);

        $message = $rule->message('age');
        $this->assertEquals($customMessage, $message);
    }

    public function test_zero_maximum(): void
    {
        $rule = new Max(0);

        $this->assertTrue($rule->validate(0));
        $this->assertTrue($rule->validate(-1));
        $this->assertTrue($rule->validate(''));  // empty string length 0
        $this->assertTrue($rule->validate([]));  // empty array count 0
        $this->assertFalse($rule->validate(1));
        $this->assertFalse($rule->validate('a')); // string length 1
        $this->assertFalse($rule->validate(['item'])); // array count 1
    }

    public function test_negative_maximum(): void
    {
        $rule = new Max(-5);

        $this->assertTrue($rule->validate(-5));  // exactly -5
        $this->assertTrue($rule->validate(-6));  // less than -5
        $this->assertTrue($rule->validate(-10)); // much less than -5
        $this->assertFalse($rule->validate(-4)); // more than -5
        $this->assertFalse($rule->validate(0));  // more than -5
        $this->assertFalse($rule->validate(5));  // much more than -5
    }

    #[DataProvider('validMaxValuesProvider')] public function test_validates_various_max_scenarios(int|float $max, mixed $value, bool $expected): void
    {
        $rule = new Max($max);
        $this->assertEquals($expected, $rule->validate($value));
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $rule = new Max(10);
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $rule = new Max(10);
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $rule = new Max(10);
        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }
}

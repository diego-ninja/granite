<?php

// tests/Unit/Validation/Rules/MinTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Min;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Min::class)] class MinTest extends TestCase
{
    public static function validMinValuesProvider(): array
    {
        return [
            // String length tests
            [3, 'abc', true],
            [3, 'ab', false],
            [0, '', true],
            [1, 'x', true],

            // Numeric tests
            [10, 10, true],
            [10, 11, true],
            [10, 9, false],
            [5.5, 5.5, true],
            [5.5, 5.6, true],
            [5.5, 5.4, false],

            // Array tests
            [2, ['a', 'b'], true],
            [2, ['a'], false],
            [0, [], true],
            [1, ['item'], true],

            // Edge cases
            [0, 0, true],
            [0, -1, false],
            [-5, -4, true],
            [-5, -6, false],
        ];
    }
    public function test_validates_string_length(): void
    {
        $rule = new Min(3);

        $this->assertTrue($rule->validate('abc'));    // exactly 3
        $this->assertTrue($rule->validate('abcd'));   // more than 3
        $this->assertTrue($rule->validate('hello'));  // more than 3
        $this->assertFalse($rule->validate('ab'));    // less than 3
        $this->assertFalse($rule->validate('a'));     // less than 3
        $this->assertFalse($rule->validate(''));      // less than 3
    }

    public function test_validates_numeric_values(): void
    {
        $rule = new Min(5);

        // Integers
        $this->assertTrue($rule->validate(5));    // exactly 5
        $this->assertTrue($rule->validate(6));    // more than 5
        $this->assertTrue($rule->validate(100));  // much more than 5
        $this->assertFalse($rule->validate(4));   // less than 5
        $this->assertFalse($rule->validate(0));   // less than 5
        $this->assertFalse($rule->validate(-1));  // less than 5

        // Floats
        $this->assertTrue($rule->validate(5.0));  // exactly 5.0
        $this->assertTrue($rule->validate(5.1));  // slightly more
        $this->assertTrue($rule->validate(10.5)); // much more
        $this->assertFalse($rule->validate(4.9)); // slightly less
        $this->assertFalse($rule->validate(0.0)); // much less
    }

    public function test_validates_array_count(): void
    {
        $rule = new Min(2);

        $this->assertTrue($rule->validate(['a', 'b']));        // exactly 2
        $this->assertTrue($rule->validate(['a', 'b', 'c']));   // more than 2
        $this->assertTrue($rule->validate([1, 2, 3, 4]));      // more than 2
        $this->assertFalse($rule->validate(['a']));            // less than 2
        $this->assertFalse($rule->validate([]));               // less than 2
    }

    public function test_validates_null_as_valid(): void
    {
        $rule = new Min(5);
        $this->assertTrue($rule->validate(null));
    }

    public function test_validates_unknown_types_as_valid(): void
    {
        $rule = new Min(5);

        $this->assertTrue($rule->validate(new stdClass()));
        $this->assertTrue($rule->validate(true));
        $this->assertTrue($rule->validate(false));
    }

    public function test_supports_float_minimum(): void
    {
        $rule = new Min(3.5);

        $this->assertTrue($rule->validate(3.5));  // exactly 3.5
        $this->assertTrue($rule->validate(3.6));  // more than 3.5
        $this->assertTrue($rule->validate(4));    // more than 3.5 (int)
        $this->assertFalse($rule->validate(3.4)); // less than 3.5
        $this->assertFalse($rule->validate(3));   // less than 3.5 (int)
    }

    public function test_returns_default_message(): void
    {
        $rule = new Min(5);
        $message = $rule->message('age');
        $this->assertEquals('age must be at least 5', $message);
    }

    public function test_returns_default_message_with_float(): void
    {
        $rule = new Min(3.14);
        $message = $rule->message('price');
        $this->assertEquals('price must be at least 3.14', $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new Min(18);
        $customMessage = 'You must be at least 18 years old';
        $rule->withMessage($customMessage);

        $message = $rule->message('age');
        $this->assertEquals($customMessage, $message);
    }

    #[DataProvider('validMinValuesProvider')] public function test_validates_various_min_scenarios(int|float $min, mixed $value, bool $expected): void
    {
        $rule = new Min($min);
        $this->assertEquals($expected, $rule->validate($value));
    }
}

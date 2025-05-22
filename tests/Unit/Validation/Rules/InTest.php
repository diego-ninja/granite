<?php
// tests/Unit/Validation/Rules/InTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\In;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\TestCase;

#[CoversClass(In::class)]
class InTest extends TestCase
{
    public function test_validates_value_in_string_list(): void
    {
        $rule = new In(['apple', 'banana', 'cherry']);

        $this->assertTrue($rule->validate('apple'));
        $this->assertTrue($rule->validate('banana'));
        $this->assertTrue($rule->validate('cherry'));
        $this->assertFalse($rule->validate('orange'));
        $this->assertFalse($rule->validate('grape'));
    }

    public function test_validates_value_in_integer_list(): void
    {
        $rule = new In([1, 2, 3, 5, 8]);

        $this->assertTrue($rule->validate(1));
        $this->assertTrue($rule->validate(3));
        $this->assertTrue($rule->validate(8));
        $this->assertFalse($rule->validate(4));
        $this->assertFalse($rule->validate(0));
        $this->assertFalse($rule->validate(10));
    }

    public function test_validates_value_in_mixed_type_list(): void
    {
        $rule = new In([1, 'two', 3.0, true, null]);

        $this->assertTrue($rule->validate(1));
        $this->assertTrue($rule->validate('two'));
        $this->assertTrue($rule->validate(3.0));
        $this->assertTrue($rule->validate(true));
        $this->assertTrue($rule->validate(null));

        $this->assertFalse($rule->validate(2));
        $this->assertFalse($rule->validate('one'));
        $this->assertFalse($rule->validate(false));
    }

    public function test_validates_null_as_valid(): void
    {
        $rule = new In(['active', 'inactive']);
        $this->assertTrue($rule->validate(null));
    }

    public function test_uses_strict_comparison(): void
    {
        $rule = new In([1, '2', 3.0]);

        // These should pass (exact matches)
        $this->assertTrue($rule->validate(1));      // int 1
        $this->assertTrue($rule->validate('2'));    // string '2'
        $this->assertTrue($rule->validate(3.0));    // float 3.0

        // These should fail (different types)
        $this->assertFalse($rule->validate('1'));   // string '1' vs int 1
        $this->assertFalse($rule->validate(2));     // int 2 vs string '2'
        $this->assertFalse($rule->validate(3));     // int 3 vs float 3.0
        $this->assertFalse($rule->validate(true));  // bool true vs int 1
    }

    public function test_validates_with_boolean_values(): void
    {
        $rule = new In([true, false, 'maybe']);

        $this->assertTrue($rule->validate(true));
        $this->assertTrue($rule->validate(false));
        $this->assertTrue($rule->validate('maybe'));

        // These should fail due to strict comparison
        $this->assertFalse($rule->validate(1));     // int 1 vs bool true
        $this->assertFalse($rule->validate(0));     // int 0 vs bool false
        $this->assertFalse($rule->validate('true'));
        $this->assertFalse($rule->validate('false'));
    }

    public function test_validates_with_empty_values(): void
    {
        $rule = new In(['', 0, false, null, []]);

        $this->assertTrue($rule->validate(''));
        $this->assertTrue($rule->validate(0));
        $this->assertTrue($rule->validate(false));
        $this->assertTrue($rule->validate(null));
        $this->assertTrue($rule->validate([]));
    }

    public function test_validates_with_single_value(): void
    {
        $rule = new In(['only']);

        $this->assertTrue($rule->validate('only'));
        $this->assertFalse($rule->validate('other'));
        $this->assertFalse($rule->validate(''));
    }

    public function test_validates_with_empty_allowed_list(): void
    {
        $rule = new In([]);

        $this->assertFalse($rule->validate('anything'));
        $this->assertFalse($rule->validate(1));
        $this->assertFalse($rule->validate(true));
        $this->assertTrue($rule->validate(null)); // null is always allowed
    }

    public function test_validates_with_duplicate_values_in_list(): void
    {
        $rule = new In(['apple', 'banana', 'apple', 'cherry', 'banana']);

        $this->assertTrue($rule->validate('apple'));
        $this->assertTrue($rule->validate('banana'));
        $this->assertTrue($rule->validate('cherry'));
        $this->assertFalse($rule->validate('orange'));
    }

    public function test_validates_with_numeric_string_values(): void
    {
        $rule = new In(['1', '2', '3.14', '0']);

        $this->assertTrue($rule->validate('1'));
        $this->assertTrue($rule->validate('2'));
        $this->assertTrue($rule->validate('3.14'));
        $this->assertTrue($rule->validate('0'));

        // Strict comparison - numeric values should fail
        $this->assertFalse($rule->validate(1));
        $this->assertFalse($rule->validate(2));
        $this->assertFalse($rule->validate(3.14));
        $this->assertFalse($rule->validate(0));
    }

    public function test_ignores_all_data_parameter(): void
    {
        $rule = new In(['active', 'inactive']);
        $allData = ['other_field' => 'value', 'status' => 'active'];

        $this->assertTrue($rule->validate('active', $allData));
        $this->assertFalse($rule->validate('pending', $allData));
    }

    public function test_returns_default_message_with_values(): void
    {
        $rule = new In(['active', 'inactive', 'pending']);
        $message = $rule->message('status');

        $this->assertEquals("status must be one of: 'active', 'inactive', 'pending'", $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new In(['active', 'inactive']);
        $customMessage = 'Status must be either active or inactive';
        $rule->withMessage($customMessage);

        $message = $rule->message('status');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $rule = new In(['test']);
        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }

    #[DataProvider('validInScenariosProvider')]
    public function test_validates_various_in_scenarios(array $allowedValues, mixed $testValue, bool $expected): void
    {
        $rule = new In($allowedValues);
        $this->assertEquals($expected, $rule->validate($testValue));
    }

    public static function validInScenariosProvider(): array
    {
        return [
            // String scenarios
            'string in list' => [['a', 'b', 'c'], 'b', true],
            'string not in list' => [['a', 'b', 'c'], 'd', false],

            // Numeric scenarios
            'int in list' => [[1, 2, 3], 2, true],
            'int not in list' => [[1, 2, 3], 4, false],
            'float in list' => [[1.1, 2.2, 3.3], 2.2, true],
            'float not in list' => [[1.1, 2.2, 3.3], 4.4, false],

            // Boolean scenarios
            'true in list' => [[true, false], true, true],
            'false in list' => [[true, false], false, true],
            'bool not in list' => [[true], false, false],

            // Mixed type scenarios
            'mixed types match' => [[1, 'two', 3.0], 'two', true],
            'mixed types no match' => [[1, 'two', 3.0], 2, false],

            // Strict comparison scenarios
            'strict int vs string' => [['1'], 1, false],
            'strict string vs int' => [[1], '1', false],
            'strict bool vs int' => [[1], true, false],
            'strict int vs bool' => [[true], 1, false],

            // Edge cases
            'empty list' => [[], 'anything', false],
            'single item match' => [['only'], 'only', true],
            'single item no match' => [['only'], 'other', false],

            // Special values
            'null always valid' => [['a', 'b'], null, true],
            'empty string in list' => [['', 'a'], '', true],
            'zero in list' => [[0, 1, 2], 0, true],
            'false in list' => [[false, true], false, true],
        ];
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $rule = new In(['test']);
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $rule = new In(['test']);
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_validates_enum_like_scenarios(): void
    {
        // Test common enum-like use cases
        $statusRule = new In(['draft', 'published', 'archived']);
        $priorityRule = new In([1, 2, 3, 4, 5]);
        $typeRule = new In(['user', 'admin', 'guest']);

        // Valid statuses
        $this->assertTrue($statusRule->validate('draft'));
        $this->assertTrue($statusRule->validate('published'));
        $this->assertFalse($statusRule->validate('pending'));

        // Valid priorities
        $this->assertTrue($priorityRule->validate(1));
        $this->assertTrue($priorityRule->validate(5));
        $this->assertFalse($priorityRule->validate(6));
        $this->assertFalse($priorityRule->validate('1')); // strict comparison

        // Valid types
        $this->assertTrue($typeRule->validate('admin'));
        $this->assertFalse($typeRule->validate('superuser'));
    }

    public function test_handles_large_value_lists(): void
    {
        // Test performance with larger lists
        $largeList = range(1, 1000);
        $rule = new In($largeList);

        $this->assertTrue($rule->validate(500));
        $this->assertTrue($rule->validate(1));
        $this->assertTrue($rule->validate(1000));
        $this->assertFalse($rule->validate(1001));
        $this->assertFalse($rule->validate(0));
    }

    public function test_validates_with_object_values(): void
    {
        $obj1 = new \stdClass();
        $obj1->id = 1;

        $obj2 = new \stdClass();
        $obj2->id = 2;

        $rule = new In([$obj1, $obj2]);

        $this->assertTrue($rule->validate($obj1));
        $this->assertTrue($rule->validate($obj2));

        $obj3 = new \stdClass();
        $obj3->id = 1; // Same properties but different instance
        $this->assertFalse($rule->validate($obj3)); // Strict comparison by reference
    }
}
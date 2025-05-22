<?php
// tests/Unit/Validation/Rules/ArrayTypeTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\ArrayType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\TestCase;

#[CoversClass(ArrayType::class)]
class ArrayTypeTest extends TestCase
{
    private ArrayType $rule;

    protected function setUp(): void
    {
        $this->rule = new ArrayType();
        parent::setUp();
    }

    public function test_validates_empty_array(): void
    {
        $this->assertTrue($this->rule->validate([]));
    }

    public function test_validates_indexed_arrays(): void
    {
        $this->assertTrue($this->rule->validate([1, 2, 3]));
        $this->assertTrue($this->rule->validate(['a', 'b', 'c']));
        $this->assertTrue($this->rule->validate([true, false, null]));
        $this->assertTrue($this->rule->validate([1, 'mixed', true]));
    }

    public function test_validates_associative_arrays(): void
    {
        $this->assertTrue($this->rule->validate(['key' => 'value']));
        $this->assertTrue($this->rule->validate(['name' => 'John', 'age' => 30]));
        $this->assertTrue($this->rule->validate(['users' => [], 'count' => 0]));
    }

    public function test_validates_nested_arrays(): void
    {
        $this->assertTrue($this->rule->validate([
            'users' => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25]
            ],
            'meta' => ['total' => 2]
        ]));

        $this->assertTrue($this->rule->validate([[[['deep']]]]));
    }

    public function test_validates_mixed_array_structures(): void
    {
        $this->assertTrue($this->rule->validate([
            0 => 'indexed',
            'key' => 'associative',
            1 => 'mixed',
            'nested' => ['array' => 'value']
        ]));
    }

    public function test_validates_null_as_valid(): void
    {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_rejects_string_values(): void
    {
        $this->assertFalse($this->rule->validate(''));
        $this->assertFalse($this->rule->validate('array'));
        $this->assertFalse($this->rule->validate('["json", "array"]'));
        $this->assertFalse($this->rule->validate('a,b,c'));
    }

    public function test_rejects_numeric_values(): void
    {
        $this->assertFalse($this->rule->validate(0));
        $this->assertFalse($this->rule->validate(123));
        $this->assertFalse($this->rule->validate(-456));
        $this->assertFalse($this->rule->validate(3.14));
        $this->assertFalse($this->rule->validate(-2.71));
    }

    public function test_rejects_boolean_values(): void
    {
        $this->assertFalse($this->rule->validate(true));
        $this->assertFalse($this->rule->validate(false));
    }

    public function test_rejects_object_values(): void
    {
        $this->assertFalse($this->rule->validate(new \stdClass()));
        $this->assertFalse($this->rule->validate((object) ['key' => 'value']));

        // ArrayObject implements array-like interface but is still an object
        $this->assertFalse($this->rule->validate(new \ArrayObject(['a', 'b', 'c'])));
    }

    public function test_rejects_resource_values(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertFalse($this->rule->validate($resource));
        fclose($resource);
    }

    public function test_rejects_callable_values(): void
    {
        $this->assertFalse($this->rule->validate('strlen'));
        $this->assertFalse($this->rule->validate(fn() => 'test'));
        $this->assertFalse($this->rule->validate(function() { return 'test'; }));
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'items' => [1, 2, 3]];

        $this->assertTrue($this->rule->validate(['a', 'b'], $allData));
        $this->assertFalse($this->rule->validate('not-array', $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('items');
        $this->assertEquals('items must be an array', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('tags must be an array', $this->rule->message('tags'));
        $this->assertEquals('users must be an array', $this->rule->message('users'));
        $this->assertEquals('meta_data must be an array', $this->rule->message('meta_data'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'This field must be a list of items';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('items');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    #[DataProvider('validArrayValuesProvider')]
    public function test_validates_various_array_types(array $value): void
    {
        $this->assertTrue($this->rule->validate($value));
    }

    public static function validArrayValuesProvider(): array
    {
        return [
            'empty array' => [[]],
            'indexed numeric' => [[1, 2, 3]],
            'indexed string' => [['a', 'b', 'c']],
            'indexed mixed' => [[1, 'two', true, null]],
            'associative simple' => [['key' => 'value']],
            'associative complex' => [['name' => 'John', 'age' => 30, 'active' => true]],
            'nested arrays' => [['level1' => ['level2' => ['level3' => 'deep']]]],
            'mixed structure' => [[0 => 'indexed', 'key' => 'associative', 1 => 'mixed']],
            'array with objects' => [[new \stdClass(), (object)['test' => true]]],
            'multidimensional' => [[[1, 2], [3, 4], [5, 6]]],
        ];
    }

    #[DataProvider('invalidNonArrayValuesProvider')]
    public function test_rejects_non_array_values(mixed $value): void
    {
        $this->assertFalse($this->rule->validate($value));
    }

    public static function invalidNonArrayValuesProvider(): array
    {
        return [
            'empty string' => [''],
            'non-empty string' => ['text'],
            'json string' => ['["a","b","c"]'],
            'comma separated' => ['a,b,c'],
            'zero integer' => [0],
            'positive integer' => [123],
            'negative integer' => [-456],
            'zero float' => [0.0],
            'positive float' => [3.14],
            'negative float' => [-2.71],
            'true boolean' => [true],
            'false boolean' => [false],
            'stdClass object' => [new \stdClass()],
            'anonymous object' => [(object) ['array' => [1, 2, 3]]],
            'ArrayObject' => [new \ArrayObject([1, 2, 3])],
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

    public function test_validates_arrays_with_special_values(): void
    {
        // Arrays containing special values should still be valid arrays
        $this->assertTrue($this->rule->validate([INF, -INF, NAN]));
        $this->assertTrue($this->rule->validate([PHP_INT_MAX, PHP_INT_MIN]));
        $this->assertTrue($this->rule->validate(['unicode' => '测试', 'emoji' => '🚀']));
    }

    public function test_validates_large_arrays(): void
    {
        // Test with reasonably large arrays
        $largeArray = range(1, 1000);
        $this->assertTrue($this->rule->validate($largeArray));

        // Test with large associative array
        $largeAssoc = [];
        for ($i = 0; $i < 100; $i++) {
            $largeAssoc["key_$i"] = "value_$i";
        }
        $this->assertTrue($this->rule->validate($largeAssoc));
    }

    public function test_strict_type_checking(): void
    {
        // Only actual arrays should pass, not array-like objects
        $this->assertTrue($this->rule->validate([]));
        $this->assertTrue($this->rule->validate([1, 2, 3]));

        // Array-like objects should fail
        $this->assertFalse($this->rule->validate(new \ArrayObject()));
        $this->assertFalse($this->rule->validate(new \SplFixedArray(3)));

        // Iterator objects should fail
        $this->assertFalse($this->rule->validate(new \ArrayIterator([1, 2, 3])));
    }
}
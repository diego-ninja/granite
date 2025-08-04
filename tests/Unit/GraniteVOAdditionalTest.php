<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Ninja\Granite\GraniteVO;
use Tests\Helpers\TestCase;

class GraniteVOAdditionalTest extends TestCase
{
    public function test_from_throws_exception_with_no_arguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one argument is required');

        TestGraniteVO::from();
    }

    public function test_equals_returns_true_for_same_instance(): void
    {
        $vo = TestGraniteVO::from(['name' => 'John', 'age' => 30]);

        $this->assertTrue($vo->equals($vo));
    }

    public function test_equals_returns_true_for_same_values(): void
    {
        $vo1 = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $vo2 = TestGraniteVO::from(['name' => 'John', 'age' => 30]);

        $this->assertTrue($vo1->equals($vo2));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $vo1 = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $vo2 = TestGraniteVO::from(['name' => 'Jane', 'age' => 30]);

        $this->assertFalse($vo1->equals($vo2));
    }

    public function test_equals_returns_false_for_different_classes(): void
    {
        $vo1 = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $vo2 = AnotherTestGraniteVO::from(['name' => 'John', 'age' => 30]);

        $this->assertFalse($vo1->equals($vo2));
    }

    public function test_equals_with_array(): void
    {
        $vo = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $array = ['name' => 'John', 'age' => 30];

        $this->assertTrue($vo->equals($array));
    }

    public function test_equals_with_different_array(): void
    {
        $vo = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $array = ['name' => 'Jane', 'age' => 25];

        $this->assertFalse($vo->equals($array));
    }

    public function test_equals_with_non_comparable_type(): void
    {
        $vo = TestGraniteVO::from(['name' => 'John', 'age' => 30]);

        $this->assertFalse($vo->equals('string'));
        $this->assertFalse($vo->equals(123));
        $this->assertFalse($vo->equals(null));
    }

    public function test_with_creates_new_instance_with_modifications(): void
    {
        $original = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $modified = $original->with(['name' => 'Jane']);

        $this->assertNotSame($original, $modified);
        $this->assertEquals('John', $original->name);
        $this->assertEquals('Jane', $modified->name);
        $this->assertEquals(30, $modified->age); // Unchanged
    }

    public function test_with_multiple_modifications(): void
    {
        $original = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $modified = $original->with(['name' => 'Jane', 'age' => 25]);

        $this->assertEquals('Jane', $modified->name);
        $this->assertEquals(25, $modified->age);
    }

    public function test_with_empty_modifications(): void
    {
        $original = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $modified = $original->with([]);

        $this->assertNotSame($original, $modified);
        $this->assertEquals($original->name, $modified->name);
        $this->assertEquals($original->age, $modified->age);
    }

    public function test_with_preserves_readonly_nature(): void
    {
        $vo = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $modified = $vo->with(['name' => 'Jane']);

        // Properties should still be readonly
        $this->assertInstanceOf(TestGraniteVO::class, $modified);
        $this->assertEquals('Jane', $modified->name);
    }

    public function test_value_object_immutability(): void
    {
        $vo1 = TestGraniteVO::from(['name' => 'John', 'age' => 30]);
        $vo2 = $vo1->with(['age' => 31]);

        // Original should be unchanged
        $this->assertEquals(30, $vo1->age);
        $this->assertEquals(31, $vo2->age);

        // They should not be equal
        $this->assertFalse($vo1->equals($vo2));
    }

    public function test_equals_handles_nested_data(): void
    {
        $vo1 = ComplexTestGraniteVO::from([
            'name' => 'John',
            'address' => ['street' => '123 Main St', 'city' => 'Test City'],
        ]);

        $vo2 = ComplexTestGraniteVO::from([
            'name' => 'John',
            'address' => ['street' => '123 Main St', 'city' => 'Test City'],
        ]);

        $this->assertTrue($vo1->equals($vo2));
    }

    public function test_with_handles_nested_modifications(): void
    {
        $original = ComplexTestGraniteVO::from([
            'name' => 'John',
            'address' => ['street' => '123 Main St', 'city' => 'Test City'],
        ]);

        $modified = $original->with([
            'address' => ['street' => '456 Oak Ave', 'city' => 'New City'],
        ]);

        $this->assertEquals('John', $modified->name); // Unchanged
        $this->assertEquals(['street' => '456 Oak Ave', 'city' => 'New City'], $modified->address);
    }
}

readonly class TestGraniteVO extends GraniteVO
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

readonly class AnotherTestGraniteVO extends GraniteVO
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

readonly class ComplexTestGraniteVO extends GraniteVO
{
    public function __construct(
        public string $name,
        public array $address,
    ) {}
}

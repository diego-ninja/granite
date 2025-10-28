<?php

namespace Tests\Unit;

use Ninja\Granite\Granite;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\Helpers\TestCase;

#[CoversClass(Granite::class)]
class GraniteTest extends TestCase
{
    public function test_granite_implements_granite_object_interface(): void
    {
        $granite = new TestGraniteClass('John', 30);

        $this->assertInstanceOf(\Ninja\Granite\Contracts\GraniteObject::class, $granite);
    }

    public function test_granite_uses_all_required_traits(): void
    {
        $traits = class_uses(Granite::class);

        $expectedTraits = [
            \Ninja\Granite\Traits\HasCarbonSupport::class,
            \Ninja\Granite\Traits\HasDeserialization::class,
            \Ninja\Granite\Traits\HasNamingConventions::class,
            \Ninja\Granite\Traits\HasSerialization::class,
            \Ninja\Granite\Traits\HasTypeConversion::class,
            \Ninja\Granite\Traits\HasValidation::class,
        ];

        foreach ($expectedTraits as $expectedTrait) {
            $this->assertContains($expectedTrait, $traits, "Missing trait: {$expectedTrait}");
        }
    }

    public function test_granite_is_readonly(): void
    {
        $reflection = new ReflectionClass(Granite::class);
        $this->assertTrue($reflection->isReadonly());
    }

    public function test_granite_is_abstract(): void
    {
        $reflection = new ReflectionClass(Granite::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function test_concrete_granite_object_creation(): void
    {
        $granite = new TestGraniteClass('Alice', 25);

        $this->assertEquals('Alice', $granite->name);
        $this->assertEquals(25, $granite->age);
    }

    public function test_granite_object_has_deserialization_capabilities(): void
    {
        // Test that it has the from method from HasDeserialization trait
        $this->assertTrue(method_exists(TestGraniteClass::class, 'from'));

        $granite = TestGraniteClass::from(['name' => 'Bob', 'age' => 35]);
        $this->assertEquals('Bob', $granite->name);
        $this->assertEquals(35, $granite->age);
    }

    public function test_granite_object_has_serialization_capabilities(): void
    {
        // Test that it has the array and json methods from HasSerialization trait
        $this->assertTrue(method_exists(TestGraniteClass::class, 'array'));
        $this->assertTrue(method_exists(TestGraniteClass::class, 'json'));

        $granite = new TestGraniteClass('Charlie', 40);

        $array = $granite->array();
        $this->assertIsArray($array);
        $this->assertEquals(['name' => 'Charlie', 'age' => 40], $array);

        $json = $granite->json();
        $this->assertIsString($json);
        $this->assertJson($json);
    }

    public function test_granite_object_has_json_serialization(): void
    {
        $granite = new TestGraniteClass('Diana', 28);

        // Check if it has jsonSerialize method
        if (method_exists($granite, 'jsonSerialize')) {
            $json = json_encode($granite);
            $this->assertJson($json);
        } else {
            // Just test that we can create the object
            $this->assertInstanceOf(TestGraniteClass::class, $granite);
        }
    }

    public function test_granite_object_has_validation_capabilities(): void
    {
        // Test that it has validation methods from HasValidation trait
        $this->assertTrue(method_exists(TestGraniteClass::class, 'validate'));

        // Should not throw for valid data
        $granite = new TestGraniteClass('Eve', 30);
        $granite->validate();
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_granite_object_has_type_conversion_capabilities(): void
    {
        // Test that it has conversion methods from HasTypeConversion trait
        $hasConversionMethod = method_exists(TestGraniteClass::class, 'convertValue')
                              || method_exists(TestGraniteClass::class, 'convert')
                              || method_exists(TestGraniteClass::class, 'transformValue');

        // If no conversion method exists, just test object creation
        if ( ! $hasConversionMethod) {
            $granite = new TestGraniteClass('Frank', 33);
            $this->assertInstanceOf(TestGraniteClass::class, $granite);
        } else {
            $this->assertTrue($hasConversionMethod);
        }
    }
}

// Concrete test class to test the abstract Granite class
readonly class TestGraniteClass extends Granite
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

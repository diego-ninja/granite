<?php

namespace Tests\Unit\Traits;

use Ninja\Granite\Mapping\Conventions\CamelCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Traits\HasNamingConventions;
use Tests\Helpers\TestCase;

class HasNamingConventionsTest extends TestCase
{
    private TestClassWithNamingConventions $testClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testClass = new TestClassWithNamingConventions();
    }

    public function test_find_value_in_data_direct_php_name_match(): void
    {
        $data = ['firstName' => 'John', 'last_name' => 'Doe'];
        $result = $this->testClass->testFindValueInData($data, 'firstName', 'first_name', null);

        $this->assertEquals('John', $result);
    }

    public function test_find_value_in_data_serialized_name_match(): void
    {
        $data = ['first_name' => 'John', 'last_name' => 'Doe'];
        $result = $this->testClass->testFindValueInData($data, 'firstName', 'first_name', null);

        $this->assertEquals('John', $result);
    }

    public function test_find_value_in_data_convention_based_lookup(): void
    {
        $data = ['first_name' => 'John', 'lastName' => 'Doe'];
        $convention = new CamelCaseConvention();

        $result = $this->testClass->testFindValueInData($data, 'firstName', 'first_name', $convention);

        $this->assertEquals('John', $result);
    }

    public function test_find_value_in_data_not_found(): void
    {
        $data = ['age' => 30, 'email' => 'john@example.com'];
        $result = $this->testClass->testFindValueInData($data, 'firstName', 'first_name', null);

        $this->assertNull($result);
    }

    public function test_find_value_in_data_empty_array(): void
    {
        $data = [];
        $result = $this->testClass->testFindValueInData($data, 'firstName', 'first_name', null);

        $this->assertNull($result);
    }

    public function test_get_class_convention_with_bidirectional(): void
    {
        $result = $this->testClass->testGetClassConvention(TestClassWithBidirectionalConvention::class);

        $this->assertInstanceOf(CamelCaseConvention::class, $result);
    }

    public function test_get_class_convention_without_bidirectional(): void
    {
        $result = $this->testClass->testGetClassConvention(TestClassWithUnidirectionalConvention::class);

        $this->assertNull($result);
    }

    public function test_get_class_convention_without_attribute(): void
    {
        $result = $this->testClass->testGetClassConvention(TestClassWithNamingConventions::class);

        $this->assertNull($result);
    }

    public function test_get_class_convention_with_invalid_class(): void
    {
        $result = $this->testClass->testGetClassConvention('NonExistentClass');

        $this->assertNull($result);
    }
}

class TestClassWithNamingConventions
{
    use HasNamingConventions;

    public function testFindValueInData($data, $phpName, $serializedName, $convention)
    {
        return self::findValueInData($data, $phpName, $serializedName, $convention);
    }

    public function testGetClassConvention($class)
    {
        return self::getClassConvention($class);
    }
}

#[SerializationConvention(CamelCaseConvention::class, bidirectional: true)]
class TestClassWithBidirectionalConvention
{
    public $firstName;
}

#[SerializationConvention(CamelCaseConvention::class, bidirectional: false)]
class TestClassWithUnidirectionalConvention
{
    public $firstName;
}

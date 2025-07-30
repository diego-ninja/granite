<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Fixtures\DTOs\PersonDTO;

final class EnhancedFromMethodTest extends TestCase
{
    public function test_from_with_array_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
        ];

        $person = PersonDTO::from($data);

        $this->assertEquals('John Doe', $person->name);
        $this->assertEquals(30, $person->age);
        $this->assertEquals('john@example.com', $person->email);
    }

    public function test_from_with_json_string(): void
    {
        $json = '{"name": "Jane Smith", "age": 25, "email": "jane@example.com"}';

        $person = PersonDTO::from($json);

        $this->assertEquals('Jane Smith', $person->name);
        $this->assertEquals(25, $person->age);
        $this->assertEquals('jane@example.com', $person->email);
    }

    public function test_from_with_granite_object(): void
    {
        $originalPerson = PersonDTO::from([
            'name' => 'Bob Wilson',
            'age' => 35,
            'email' => 'bob@example.com',
        ]);

        $newPerson = PersonDTO::from($originalPerson);

        $this->assertEquals('Bob Wilson', $newPerson->name);
        $this->assertEquals(35, $newPerson->age);
        $this->assertEquals('bob@example.com', $newPerson->email);
    }

    public function test_from_with_named_parameters(): void
    {
        $person = PersonDTO::from(
            name: 'Alice Johnson',
            age: 28,
            email: 'alice@example.com',
        );

        $this->assertEquals('Alice Johnson', $person->name);
        $this->assertEquals(28, $person->age);
        $this->assertEquals('alice@example.com', $person->email);
    }

    public function test_from_with_partial_named_parameters(): void
    {
        $person = PersonDTO::from(
            name: 'Charlie Brown',
            age: 40,
        );

        $this->assertEquals('Charlie Brown', $person->name);
        $this->assertEquals(40, $person->age);
        // Email should be uninitialized since it wasn't provided
    }

    public function test_from_with_mixed_data_and_named_parameters(): void
    {
        $baseData = [
            'name' => 'David Miller',
            'age' => 45,
            'email' => 'david@example.com',
        ];

        // Override specific fields with named parameters
        $person = PersonDTO::from(
            $baseData,
            name: 'David M. Miller',  // Override name
            age: 46,                   // Override age
            // email should remain from baseData
        );

        $this->assertEquals('David M. Miller', $person->name);
        $this->assertEquals(46, $person->age);
        $this->assertEquals('david@example.com', $person->email);
    }

    public function test_from_with_json_and_named_parameter_override(): void
    {
        $json = '{"name": "Eva Brown", "age": 32, "email": "eva@example.com"}';

        $person = PersonDTO::from(
            $json,
            age: 33,  // Override age from JSON
        );

        $this->assertEquals('Eva Brown', $person->name);
        $this->assertEquals(33, $person->age);  // Overridden value
        $this->assertEquals('eva@example.com', $person->email);
    }

    public function test_from_with_granite_object_and_named_parameter_override(): void
    {
        $originalPerson = PersonDTO::from([
            'name' => 'Frank Wilson',
            'age' => 50,
            'email' => 'frank@example.com',
        ]);

        $person = PersonDTO::from(
            $originalPerson,
            email: 'frank.wilson@example.com',  // Override email
        );

        $this->assertEquals('Frank Wilson', $person->name);
        $this->assertEquals(50, $person->age);
        $this->assertEquals('frank.wilson@example.com', $person->email);  // Overridden
    }

    public function test_from_empty_creates_object_with_uninitialized_properties(): void
    {
        $person = PersonDTO::from();

        // Properties should exist but be uninitialized
        $reflection = new ReflectionClass($person);

        $nameProperty = $reflection->getProperty('name');
        $ageProperty = $reflection->getProperty('age');
        $emailProperty = $reflection->getProperty('email');

        $this->assertFalse($nameProperty->isInitialized($person));
        $this->assertFalse($ageProperty->isInitialized($person));
        $this->assertFalse($emailProperty->isInitialized($person));
    }

    public function test_serialization_works_with_named_parameter_created_objects(): void
    {
        $person = PersonDTO::from(
            name: 'Grace Lee',
            age: 29,
            email: 'grace@example.com',
        );

        $array = $person->array();
        $json = $person->json();

        $this->assertEquals([
            'name' => 'Grace Lee',
            'age' => 29,
            'email' => 'grace@example.com',
        ], $array);

        $this->assertJson($json);
        $this->assertEquals($array, json_decode($json, true));
    }
}

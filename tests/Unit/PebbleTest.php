<?php

namespace Tests\Unit;

use InvalidArgumentException;
use JsonSerializable;
use Ninja\Granite\Pebble;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Pebble::class)]
class PebbleTest extends TestCase
{
    public function test_can_create_from_array(): void
    {
        $data = ['name' => 'John', 'age' => 30, 'email' => 'john@example.com'];
        $pebble = Pebble::from($data);

        $this->assertEquals('John', $pebble->name);
        $this->assertEquals(30, $pebble->age);
        $this->assertEquals('john@example.com', $pebble->email);
    }

    public function test_can_create_from_json_string(): void
    {
        $json = '{"name": "Jane", "age": 25, "city": "New York"}';
        $pebble = Pebble::from($json);

        $this->assertEquals('Jane', $pebble->name);
        $this->assertEquals(25, $pebble->age);
        $this->assertEquals('New York', $pebble->city);
    }

    public function test_throws_exception_for_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string provided');

        Pebble::from('not valid json');
    }

    public function test_can_create_from_object_with_public_properties(): void
    {
        $obj = new class () {
            public string $name = 'Alice';
            public int $age = 28;
            public string $role = 'Developer';
        };

        $pebble = Pebble::from($obj);

        $this->assertEquals('Alice', $pebble->name);
        $this->assertEquals(28, $pebble->age);
        $this->assertEquals('Developer', $pebble->role);
    }

    public function test_can_create_from_object_with_toarray_method(): void
    {
        $obj = new class () {
            public function toArray(): array
            {
                return [
                    'name' => 'Bob',
                    'email' => 'bob@example.com',
                    'active' => true,
                ];
            }
        };

        $pebble = Pebble::from($obj);

        $this->assertEquals('Bob', $pebble->name);
        $this->assertEquals('bob@example.com', $pebble->email);
        $this->assertTrue($pebble->active);
    }

    public function test_can_create_from_json_serializable_object(): void
    {
        $obj = new class () implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return [
                    'id' => 123,
                    'title' => 'Test',
                    'status' => 'published',
                ];
            }
        };

        $pebble = Pebble::from($obj);

        $this->assertEquals(123, $pebble->id);
        $this->assertEquals('Test', $pebble->title);
        $this->assertEquals('published', $pebble->status);
    }

    public function test_extracts_getters_from_object(): void
    {
        $obj = new class () {
            public string $name = 'Charlie';

            public function getEmail(): string
            {
                return 'charlie@example.com';
            }

            public function isActive(): bool
            {
                return true;
            }

            public function hasPermission(): bool
            {
                return false;
            }
        };

        $pebble = Pebble::from($obj);

        $this->assertEquals('Charlie', $pebble->name);
        $this->assertEquals('charlie@example.com', $pebble->email);
        $this->assertTrue($pebble->active);
        $this->assertFalse($pebble->permission);
    }

    public function test_public_properties_take_precedence_over_getters(): void
    {
        $obj = new class () {
            public string $name = 'Direct Property';

            public function getName(): string
            {
                return 'From Getter';
            }
        };

        $pebble = Pebble::from($obj);

        // Public property should win
        $this->assertEquals('Direct Property', $pebble->name);
    }

    public function test_returns_null_for_nonexistent_property(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->assertNull($pebble->nonexistent);
    }

    public function test_isset_returns_true_for_existing_property(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'value' => null]);

        $this->assertTrue(isset($pebble->name));
        $this->assertTrue(isset($pebble->value)); // even for null values
        $this->assertFalse(isset($pebble->nonexistent));
    }

    public function test_cannot_set_properties(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify Pebble properties');

        $pebble->name = 'New Name';
    }

    public function test_cannot_unset_properties(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot unset Pebble properties');

        unset($pebble->name);
    }

    public function test_array_method_returns_all_data(): void
    {
        $data = ['name' => 'Test', 'age' => 30, 'active' => true];
        $pebble = Pebble::from($data);

        $this->assertEquals($data, $pebble->array());
    }

    public function test_json_method_returns_valid_json(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble = Pebble::from($data);

        $json = $pebble->json();
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    public function test_json_serialize_returns_data(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble = Pebble::from($data);

        $this->assertEquals($data, $pebble->jsonSerialize());
    }

    public function test_can_be_json_encoded(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble = Pebble::from($data);

        $json = json_encode($pebble);
        $decoded = json_decode($json, true);

        $this->assertEquals($data, $decoded);
    }

    public function test_equals_returns_true_for_same_instance(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->assertTrue($pebble->equals($pebble));
    }

    public function test_equals_returns_true_for_identical_pebbles(): void
    {
        $pebble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $pebble2 = Pebble::from(['name' => 'Test', 'age' => 30]);

        $this->assertTrue($pebble1->equals($pebble2));
    }

    public function test_equals_returns_false_for_different_pebbles(): void
    {
        $pebble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $pebble2 = Pebble::from(['name' => 'Test', 'age' => 31]);

        $this->assertFalse($pebble1->equals($pebble2));
    }

    public function test_equals_returns_true_for_matching_array(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble = Pebble::from($data);

        $this->assertTrue($pebble->equals($data));
    }

    public function test_equals_returns_false_for_different_array(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $differentArray = ['name' => 'Test', 'age' => 31];

        $this->assertFalse($pebble->equals($differentArray));
    }

    public function test_equals_returns_false_for_other_types(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->assertFalse($pebble->equals('string'));
        $this->assertFalse($pebble->equals(123));
        $this->assertFalse($pebble->equals(new stdClass()));
    }

    public function test_is_empty_returns_true_for_empty_data(): void
    {
        $pebble = Pebble::from([]);

        $this->assertTrue($pebble->isEmpty());
    }

    public function test_is_empty_returns_false_for_non_empty_data(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->assertFalse($pebble->isEmpty());
    }

    public function test_count_returns_number_of_properties(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30, 'active' => true]);

        $this->assertEquals(3, $pebble->count());
    }

    public function test_has_checks_property_existence(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'value' => null]);

        $this->assertTrue($pebble->has('name'));
        $this->assertTrue($pebble->has('value')); // even for null
        $this->assertFalse($pebble->has('nonexistent'));
    }

    public function test_get_returns_property_value(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30]);

        $this->assertEquals('Test', $pebble->get('name'));
        $this->assertEquals(30, $pebble->get('age'));
    }

    public function test_get_returns_default_for_nonexistent_property(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->assertEquals('default', $pebble->get('nonexistent', 'default'));
        $this->assertNull($pebble->get('nonexistent'));
    }

    public function test_only_returns_specified_properties(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);
        $filtered = $pebble->only(['name', 'email']);

        $this->assertEquals('Test', $filtered->name);
        $this->assertEquals('test@example.com', $filtered->email);
        $this->assertNull($filtered->age);
        $this->assertEquals(2, $filtered->count());
    }

    public function test_except_removes_specified_properties(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);
        $filtered = $pebble->except(['age']);

        $this->assertEquals('Test', $filtered->name);
        $this->assertEquals('test@example.com', $filtered->email);
        $this->assertNull($filtered->age);
        $this->assertEquals(2, $filtered->count());
    }

    public function test_merge_combines_data(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $merged = $pebble->merge(['email' => 'test@example.com', 'active' => true]);

        $this->assertEquals('Test', $merged->name);
        $this->assertEquals(30, $merged->age);
        $this->assertEquals('test@example.com', $merged->email);
        $this->assertTrue($merged->active);
        $this->assertEquals(4, $merged->count());
    }

    public function test_merge_overwrites_existing_properties(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $merged = $pebble->merge(['age' => 31, 'email' => 'test@example.com']);

        $this->assertEquals('Test', $merged->name);
        $this->assertEquals(31, $merged->age); // overwritten
        $this->assertEquals('test@example.com', $merged->email);
    }

    public function test_merge_does_not_modify_original(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $merged = $pebble->merge(['age' => 31]);

        // Original unchanged
        $this->assertEquals(30, $pebble->age);
        // Merged has new value
        $this->assertEquals(31, $merged->age);
    }

    public function test_to_string_returns_json(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $string = (string) $pebble;

        $this->assertJson($string);
        $decoded = json_decode($string, true);
        $this->assertEquals(['name' => 'Test', 'age' => 30], $decoded);
    }

    public function test_debug_info_returns_data(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble = Pebble::from($data);

        $debugInfo = $pebble->__debugInfo();
        $this->assertEquals($data, $debugInfo);
    }

    public function test_works_with_nested_arrays(): void
    {
        $data = [
            'name' => 'Test',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
            ],
        ];

        $pebble = Pebble::from($data);

        $this->assertEquals('Test', $pebble->name);
        $this->assertEquals(['street' => '123 Main St', 'city' => 'New York'], $pebble->address);
    }

    public function test_handles_various_data_types(): void
    {
        $data = [
            'string' => 'text',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
        ];

        $pebble = Pebble::from($data);

        $this->assertEquals('text', $pebble->string);
        $this->assertEquals(42, $pebble->int);
        $this->assertEquals(3.14, $pebble->float);
        $this->assertTrue($pebble->bool);
        $this->assertNull($pebble->null);
        $this->assertEquals([1, 2, 3], $pebble->array);
    }

    public function test_skips_getters_requiring_parameters(): void
    {
        $obj = new class () {
            public function getValue(string $key): string
            {
                return "value-{$key}";
            }

            public function getName(): string
            {
                return 'test';
            }
        };

        $pebble = Pebble::from($obj);

        // Should have name from getName() but not value from getValue($key)
        $this->assertEquals('test', $pebble->name);
        $this->assertNull($pebble->value);
    }

    public function test_only_extracts_public_getters(): void
    {
        $obj = new class () {
            protected function getProtected(): string
            {
                return 'protected';
            }

            private function getPrivate(): string
            {
                return 'private';
            }

            public function getPublic(): string
            {
                return 'public';
            }
        };

        $pebble = Pebble::from($obj);

        $this->assertEquals('public', $pebble->public);
        $this->assertNull($pebble->protected);
        $this->assertNull($pebble->private);
    }

    public function test_fingerprint_returns_consistent_hash(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble = Pebble::from($data);

        $fingerprint1 = $pebble->fingerprint();
        $fingerprint2 = $pebble->fingerprint();

        $this->assertEquals($fingerprint1, $fingerprint2);
        $this->assertNotEmpty($fingerprint1);
        $this->assertIsString($fingerprint1);
    }

    public function test_identical_pebbles_have_same_fingerprint(): void
    {
        $data = ['name' => 'Test', 'age' => 30, 'email' => 'test@example.com'];
        $pebble1 = Pebble::from($data);
        $pebble2 = Pebble::from($data);

        $this->assertEquals($pebble1->fingerprint(), $pebble2->fingerprint());
    }

    public function test_different_pebbles_have_different_fingerprints(): void
    {
        $pebble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $pebble2 = Pebble::from(['name' => 'Test', 'age' => 31]);

        $this->assertNotEquals($pebble1->fingerprint(), $pebble2->fingerprint());
    }

    public function test_fingerprint_is_order_insensitive(): void
    {
        // Note: JSON-based fingerprinting normalizes key order, so same data = same fingerprint
        $pebble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $pebble2 = Pebble::from(['age' => 30, 'name' => 'Test']);

        // These will have the same fingerprints despite different key order
        $this->assertEquals($pebble1->fingerprint(), $pebble2->fingerprint());
    }

    public function test_equals_uses_fingerprint_for_pebble_comparison(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $pebble1 = Pebble::from($data);
        $pebble2 = Pebble::from($data);

        // Force fingerprint calculation
        $pebble1->fingerprint();
        $pebble2->fingerprint();

        // equals() should use fingerprint comparison
        $this->assertTrue($pebble1->equals($pebble2));
    }

    public function test_can_access_via_array_syntax(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);

        $this->assertEquals('Test', $pebble['name']);
        $this->assertEquals(30, $pebble['age']);
        $this->assertEquals('test@example.com', $pebble['email']);
    }

    public function test_array_access_returns_null_for_nonexistent_key(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->assertNull($pebble['nonexistent']);
    }

    public function test_isset_works_with_array_syntax(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'value' => null]);

        $this->assertTrue(isset($pebble['name']));
        $this->assertTrue(isset($pebble['value'])); // even for null
        $this->assertFalse(isset($pebble['nonexistent']));
    }

    public function test_cannot_set_via_array_syntax(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify Pebble properties via array access');

        $pebble['name'] = 'New Name';
    }

    public function test_cannot_unset_via_array_syntax(): void
    {
        $pebble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot unset Pebble properties via array access');

        unset($pebble['name']);
    }

    public function test_count_function_works(): void
    {
        $pebble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);

        // count() function should work due to Countable interface
        $this->assertEquals(3, count($pebble));
    }

    public function test_countable_returns_zero_for_empty_pebble(): void
    {
        $pebble = Pebble::from([]);

        $this->assertEquals(0, count($pebble));
    }
}

<?php

namespace Tests\Unit;

use InvalidArgumentException;
use JsonSerializable;
use Ninja\Granite\Pebble;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Pebble::class)]
class PeebleTest extends TestCase
{
    public function test_can_create_from_array(): void
    {
        $data = ['name' => 'John', 'age' => 30, 'email' => 'john@example.com'];
        $peeble = Pebble::from($data);

        $this->assertEquals('John', $peeble->name);
        $this->assertEquals(30, $peeble->age);
        $this->assertEquals('john@example.com', $peeble->email);
    }

    public function test_can_create_from_json_string(): void
    {
        $json = '{"name": "Jane", "age": 25, "city": "New York"}';
        $peeble = Pebble::from($json);

        $this->assertEquals('Jane', $peeble->name);
        $this->assertEquals(25, $peeble->age);
        $this->assertEquals('New York', $peeble->city);
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

        $peeble = Pebble::from($obj);

        $this->assertEquals('Alice', $peeble->name);
        $this->assertEquals(28, $peeble->age);
        $this->assertEquals('Developer', $peeble->role);
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

        $peeble = Pebble::from($obj);

        $this->assertEquals('Bob', $peeble->name);
        $this->assertEquals('bob@example.com', $peeble->email);
        $this->assertTrue($peeble->active);
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

        $peeble = Pebble::from($obj);

        $this->assertEquals(123, $peeble->id);
        $this->assertEquals('Test', $peeble->title);
        $this->assertEquals('published', $peeble->status);
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

        $peeble = Pebble::from($obj);

        $this->assertEquals('Charlie', $peeble->name);
        $this->assertEquals('charlie@example.com', $peeble->email);
        $this->assertTrue($peeble->active);
        $this->assertFalse($peeble->permission);
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

        $peeble = Pebble::from($obj);

        // Public property should win
        $this->assertEquals('Direct Property', $peeble->name);
    }

    public function test_returns_null_for_nonexistent_property(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->assertNull($peeble->nonexistent);
    }

    public function test_isset_returns_true_for_existing_property(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'value' => null]);

        $this->assertTrue(isset($peeble->name));
        $this->assertTrue(isset($peeble->value)); // even for null values
        $this->assertFalse(isset($peeble->nonexistent));
    }

    public function test_cannot_set_properties(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify Pebble properties');

        $peeble->name = 'New Name';
    }

    public function test_cannot_unset_properties(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot unset Pebble properties');

        unset($peeble->name);
    }

    public function test_array_method_returns_all_data(): void
    {
        $data = ['name' => 'Test', 'age' => 30, 'active' => true];
        $peeble = Pebble::from($data);

        $this->assertEquals($data, $peeble->array());
    }

    public function test_json_method_returns_valid_json(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble = Pebble::from($data);

        $json = $peeble->json();
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    public function test_json_serialize_returns_data(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble = Pebble::from($data);

        $this->assertEquals($data, $peeble->jsonSerialize());
    }

    public function test_can_be_json_encoded(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble = Pebble::from($data);

        $json = json_encode($peeble);
        $decoded = json_decode($json, true);

        $this->assertEquals($data, $decoded);
    }

    public function test_equals_returns_true_for_same_instance(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->assertTrue($peeble->equals($peeble));
    }

    public function test_equals_returns_true_for_identical_peebles(): void
    {
        $peeble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $peeble2 = Pebble::from(['name' => 'Test', 'age' => 30]);

        $this->assertTrue($peeble1->equals($peeble2));
    }

    public function test_equals_returns_false_for_different_peebles(): void
    {
        $peeble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $peeble2 = Pebble::from(['name' => 'Test', 'age' => 31]);

        $this->assertFalse($peeble1->equals($peeble2));
    }

    public function test_equals_returns_true_for_matching_array(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble = Pebble::from($data);

        $this->assertTrue($peeble->equals($data));
    }

    public function test_equals_returns_false_for_different_array(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $differentArray = ['name' => 'Test', 'age' => 31];

        $this->assertFalse($peeble->equals($differentArray));
    }

    public function test_equals_returns_false_for_other_types(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->assertFalse($peeble->equals('string'));
        $this->assertFalse($peeble->equals(123));
        $this->assertFalse($peeble->equals(new stdClass()));
    }

    public function test_is_empty_returns_true_for_empty_data(): void
    {
        $peeble = Pebble::from([]);

        $this->assertTrue($peeble->isEmpty());
    }

    public function test_is_empty_returns_false_for_non_empty_data(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->assertFalse($peeble->isEmpty());
    }

    public function test_count_returns_number_of_properties(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30, 'active' => true]);

        $this->assertEquals(3, $peeble->count());
    }

    public function test_has_checks_property_existence(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'value' => null]);

        $this->assertTrue($peeble->has('name'));
        $this->assertTrue($peeble->has('value')); // even for null
        $this->assertFalse($peeble->has('nonexistent'));
    }

    public function test_get_returns_property_value(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30]);

        $this->assertEquals('Test', $peeble->get('name'));
        $this->assertEquals(30, $peeble->get('age'));
    }

    public function test_get_returns_default_for_nonexistent_property(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->assertEquals('default', $peeble->get('nonexistent', 'default'));
        $this->assertNull($peeble->get('nonexistent'));
    }

    public function test_only_returns_specified_properties(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);
        $filtered = $peeble->only(['name', 'email']);

        $this->assertEquals('Test', $filtered->name);
        $this->assertEquals('test@example.com', $filtered->email);
        $this->assertNull($filtered->age);
        $this->assertEquals(2, $filtered->count());
    }

    public function test_except_removes_specified_properties(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);
        $filtered = $peeble->except(['age']);

        $this->assertEquals('Test', $filtered->name);
        $this->assertEquals('test@example.com', $filtered->email);
        $this->assertNull($filtered->age);
        $this->assertEquals(2, $filtered->count());
    }

    public function test_merge_combines_data(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $merged = $peeble->merge(['email' => 'test@example.com', 'active' => true]);

        $this->assertEquals('Test', $merged->name);
        $this->assertEquals(30, $merged->age);
        $this->assertEquals('test@example.com', $merged->email);
        $this->assertTrue($merged->active);
        $this->assertEquals(4, $merged->count());
    }

    public function test_merge_overwrites_existing_properties(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $merged = $peeble->merge(['age' => 31, 'email' => 'test@example.com']);

        $this->assertEquals('Test', $merged->name);
        $this->assertEquals(31, $merged->age); // overwritten
        $this->assertEquals('test@example.com', $merged->email);
    }

    public function test_merge_does_not_modify_original(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $merged = $peeble->merge(['age' => 31]);

        // Original unchanged
        $this->assertEquals(30, $peeble->age);
        // Merged has new value
        $this->assertEquals(31, $merged->age);
    }

    public function test_to_string_returns_json(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30]);
        $string = (string) $peeble;

        $this->assertJson($string);
        $decoded = json_decode($string, true);
        $this->assertEquals(['name' => 'Test', 'age' => 30], $decoded);
    }

    public function test_debug_info_returns_data(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble = Pebble::from($data);

        $debugInfo = $peeble->__debugInfo();
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

        $peeble = Pebble::from($data);

        $this->assertEquals('Test', $peeble->name);
        $this->assertEquals(['street' => '123 Main St', 'city' => 'New York'], $peeble->address);
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

        $peeble = Pebble::from($data);

        $this->assertEquals('text', $peeble->string);
        $this->assertEquals(42, $peeble->int);
        $this->assertEquals(3.14, $peeble->float);
        $this->assertTrue($peeble->bool);
        $this->assertNull($peeble->null);
        $this->assertEquals([1, 2, 3], $peeble->array);
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

        $peeble = Pebble::from($obj);

        // Should have name from getName() but not value from getValue($key)
        $this->assertEquals('test', $peeble->name);
        $this->assertNull($peeble->value);
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

        $peeble = Pebble::from($obj);

        $this->assertEquals('public', $peeble->public);
        $this->assertNull($peeble->protected);
        $this->assertNull($peeble->private);
    }

    public function test_fingerprint_returns_consistent_hash(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble = Pebble::from($data);

        $fingerprint1 = $peeble->fingerprint();
        $fingerprint2 = $peeble->fingerprint();

        $this->assertEquals($fingerprint1, $fingerprint2);
        $this->assertNotEmpty($fingerprint1);
        $this->assertIsString($fingerprint1);
    }

    public function test_identical_peebles_have_same_fingerprint(): void
    {
        $data = ['name' => 'Test', 'age' => 30, 'email' => 'test@example.com'];
        $peeble1 = Pebble::from($data);
        $peeble2 = Pebble::from($data);

        $this->assertEquals($peeble1->fingerprint(), $peeble2->fingerprint());
    }

    public function test_different_peebles_have_different_fingerprints(): void
    {
        $peeble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $peeble2 = Pebble::from(['name' => 'Test', 'age' => 31]);

        $this->assertNotEquals($peeble1->fingerprint(), $peeble2->fingerprint());
    }

    public function test_fingerprint_changes_with_order(): void
    {
        // Note: serialize() is order-sensitive, so different key orders = different fingerprints
        $peeble1 = Pebble::from(['name' => 'Test', 'age' => 30]);
        $peeble2 = Pebble::from(['age' => 30, 'name' => 'Test']);

        // These will have different fingerprints due to key order
        $this->assertNotEquals($peeble1->fingerprint(), $peeble2->fingerprint());
    }

    public function test_equals_uses_fingerprint_for_pebble_comparison(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $peeble1 = Pebble::from($data);
        $peeble2 = Pebble::from($data);

        // Force fingerprint calculation
        $peeble1->fingerprint();
        $peeble2->fingerprint();

        // equals() should use fingerprint comparison
        $this->assertTrue($peeble1->equals($peeble2));
    }

    public function test_can_access_via_array_syntax(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);

        $this->assertEquals('Test', $peeble['name']);
        $this->assertEquals(30, $peeble['age']);
        $this->assertEquals('test@example.com', $peeble['email']);
    }

    public function test_array_access_returns_null_for_nonexistent_key(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->assertNull($peeble['nonexistent']);
    }

    public function test_isset_works_with_array_syntax(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'value' => null]);

        $this->assertTrue(isset($peeble['name']));
        $this->assertTrue(isset($peeble['value'])); // even for null
        $this->assertFalse(isset($peeble['nonexistent']));
    }

    public function test_cannot_set_via_array_syntax(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify Pebble properties via array access');

        $peeble['name'] = 'New Name';
    }

    public function test_cannot_unset_via_array_syntax(): void
    {
        $peeble = Pebble::from(['name' => 'Test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot unset Pebble properties via array access');

        unset($peeble['name']);
    }

    public function test_count_function_works(): void
    {
        $peeble = Pebble::from(['name' => 'Test', 'age' => 30, 'email' => 'test@example.com']);

        // count() function should work due to Countable interface
        $this->assertEquals(3, count($peeble));
    }

    public function test_countable_returns_zero_for_empty_pebble(): void
    {
        $peeble = Pebble::from([]);

        $this->assertEquals(0, count($peeble));
    }
}

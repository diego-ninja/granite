<?php

namespace Tests\Unit;

use ArrayObject;
use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Ninja\Granite\Pebble;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;
use Throwable;

#[CoversClass(Pebble::class)]
class PebbleTest extends TestCase
{
    /**
     * @return array<string, array{Closure(Pebble): array{object, DateTime}}>
     */
    public static function mutableAccessorProvider(): array
    {
        return [
            'array()' => [static function (Pebble $pebble): array {
                $data = $pebble->array();

                return [$data['profile'], $data['occurredAt']];
            }],
            'get()' => [static fn(Pebble $pebble): array => [
                $pebble->get('profile'),
                $pebble->get('occurredAt'),
            ]],
            '__get()' => [static fn(Pebble $pebble): array => [
                $pebble->profile,
                $pebble->occurredAt,
            ]],
            'offsetGet()' => [static fn(Pebble $pebble): array => [
                $pebble['profile'],
                $pebble['occurredAt'],
            ]],
        ];
    }
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
        $obj = new class {
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
        $obj = new class {
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
        $obj = new class implements JsonSerializable {
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
        $obj = new class {
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
        $obj = new class {
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
        $obj = new class {
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
        $obj = new class {
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

    public function test_nested_array_source_is_snapshotted_at_creation_time(): void
    {
        $source = ['profile' => ['name' => 'Original']];
        $pebble = Pebble::from($source);

        $source['profile']['name'] = 'Changed';

        $this->assertSame('Original', $pebble->array()['profile']['name']);
    }

    public function test_mutating_source_objects_after_creation_does_not_change_snapshot_or_fingerprint(): void
    {
        $profile = (object) ['name' => 'Original'];
        $occurredAt = new DateTime('2026-09-06 10:11:12.123456', new DateTimeZone('Europe/Madrid'));
        $source = (object) [
            'profile' => $profile,
            'occurredAt' => $occurredAt,
        ];
        $pebble = Pebble::from($source);
        $fingerprint = $pebble->fingerprint();

        $profile->name = 'Changed';
        $occurredAt->modify('+1 day');

        $snapshot = $pebble->array();
        $this->assertSame('Original', $snapshot['profile']->name);
        $this->assertSame('2026-09-06 10:11:12.123456 Europe/Madrid', $snapshot['occurredAt']->format('Y-m-d H:i:s.u e'));
        $this->assertSame($fingerprint, $pebble->fingerprint());
    }

    #[DataProvider('mutableAccessorProvider')]
    public function test_read_accessors_return_defensive_copies(Closure $readAccessor): void
    {
        $pebble = Pebble::from([
            'profile' => (object) ['name' => 'Original'],
            'occurredAt' => new DateTime('2026-09-06 10:11:12.123456', new DateTimeZone('Europe/Madrid')),
        ]);
        $fingerprint = $pebble->fingerprint();

        [$profile, $occurredAt] = $readAccessor($pebble);
        $profile->name = 'Changed';
        $occurredAt->modify('+1 day');

        $snapshot = $pebble->array();
        $this->assertSame('Original', $snapshot['profile']->name);
        $this->assertSame('2026-09-06 10:11:12.123456 Europe/Madrid', $snapshot['occurredAt']->format('Y-m-d H:i:s.u e'));
        $this->assertSame($fingerprint, $pebble->fingerprint());
    }

    public function test_date_fingerprints_include_microseconds(): void
    {
        $first = Pebble::from(['value' => new DateTimeImmutable('2026-09-06T10:11:12.123456+00:00')]);
        $second = Pebble::from(['value' => new DateTimeImmutable('2026-09-06T10:11:12.654321+00:00')]);

        $this->assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function test_date_fingerprints_include_timezone_name(): void
    {
        $first = Pebble::from([
            'value' => new DateTimeImmutable('2026-01-15 10:11:12', new DateTimeZone('Europe/Madrid')),
        ]);
        $second = Pebble::from([
            'value' => new DateTimeImmutable('2026-01-15 10:11:12', new DateTimeZone('Africa/Algiers')),
        ]);

        $this->assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function test_date_fingerprints_include_concrete_class(): void
    {
        $mutable = Pebble::from(['value' => new DateTime('2026-09-06T10:11:12.123456+00:00')]);
        $immutable = Pebble::from(['value' => new DateTimeImmutable('2026-09-06T10:11:12.123456+00:00')]);

        $this->assertNotSame($mutable->fingerprint(), $immutable->fingerprint());
    }

    public function test_enum_fingerprints_include_concrete_class(): void
    {
        $first = Pebble::from(['value' => PebbleFirstStatus::Ready]);
        $second = Pebble::from(['value' => PebbleSecondStatus::Ready]);

        $this->assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function test_get_returns_present_null_instead_of_default(): void
    {
        $pebble = Pebble::from(['present-null' => null]);

        $this->assertNull($pebble->get('present-null', 'default'));
    }

    public function test_private_to_array_falls_back_to_json_serializable(): void
    {
        $source = new class implements JsonSerializable {
            private function toArray(): array
            {
                throw new LogicException('Private adapter must not be invoked');
            }

            public function jsonSerialize(): array
            {
                return ['source' => 'json'];
            }
        };

        try {
            $pebble = Pebble::from($source);
        } catch (Throwable $exception) {
            self::fail('Private toArray() was invoked: ' . $exception->getMessage());
        }

        $this->assertSame('json', $pebble->source);
    }

    public function test_private_to_array_falls_back_to_public_properties(): void
    {
        $source = new class {
            public string $source = 'property';

            private function toArray(): array
            {
                throw new LogicException('Private adapter must not be invoked');
            }
        };

        try {
            $pebble = Pebble::from($source);
        } catch (Throwable $exception) {
            self::fail('Private toArray() was invoked: ' . $exception->getMessage());
        }

        $this->assertSame('property', $pebble->source);
    }

    public function test_resources_are_rejected_as_unsnapshotable(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot snapshot resource');

        try {
            Pebble::from(['resource' => $resource]);
        } finally {
            fclose($resource);
        }
    }

    public function test_autoreferential_arrays_are_rejected_deterministically(): void
    {
        if ( ! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open() is required to bound this regression test.');
        }

        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = <<<'PHP'
            require %s;

            $recursive = [];
            $recursive['self'] = &$recursive;

            try {
                \Ninja\Granite\Pebble::from(['value' => $recursive]);
            } catch (\InvalidArgumentException $exception) {
                exit(str_contains($exception->getMessage(), 'recursive array') ? 0 : 2);
            }

            exit(3);
            PHP;
        $process = proc_open(
            [PHP_BINARY, '-d', 'memory_limit=64M', '-r', sprintf($script, var_export($autoloadPath, true))],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $this->assertIsResource($process);
        $startedAt = microtime(true);
        $status = proc_get_status($process);

        while ($status['running'] && microtime(true) - $startedAt < 2.0) {
            usleep(10_000);
            $status = proc_get_status($process);
        }

        if ($status['running']) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);

            $this->fail('Pebble::from() did not reject the recursive array within 2 seconds.');
        }

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);
        if (-1 === $exitCode) {
            $exitCode = $status['exitcode'];
        }

        $this->assertSame(0, $exitCode, trim($output . "\n" . $errors));
    }

    public function test_array_object_state_is_preserved_or_explicitly_rejected(): void
    {
        try {
            $first = Pebble::from(['value' => new ArrayObject(['name' => 'first'])]);
            $second = Pebble::from(['value' => new ArrayObject(['name' => 'second'])]);
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cannot snapshot object', $exception->getMessage());
            return;
        }

        $firstValue = $first->get('value');
        $secondValue = $second->get('value');

        $this->assertInstanceOf(ArrayObject::class, $firstValue);
        $this->assertInstanceOf(ArrayObject::class, $secondValue);
        $this->assertSame(['name' => 'first'], $firstValue->getArrayCopy());
        $this->assertSame(['name' => 'second'], $secondValue->getArrayCopy());
        $this->assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function test_userland_array_object_subclass_is_rejected_instead_of_losing_internal_parent_state(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot snapshot object');

        Pebble::from(['value' => new PebbleArrayObjectChild(['name' => 'original'])]);
    }

    public function test_inherited_private_state_is_preserved_or_explicitly_rejected(): void
    {
        try {
            $first = Pebble::from(['value' => new PebblePrivateStateChild('first')]);
            $second = Pebble::from(['value' => new PebblePrivateStateChild('second')]);
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cannot snapshot object', $exception->getMessage());
            return;
        }

        $firstValue = $first->get('value');
        $secondValue = $second->get('value');

        $this->assertInstanceOf(PebblePrivateStateChild::class, $firstValue);
        $this->assertInstanceOf(PebblePrivateStateChild::class, $secondValue);
        $this->assertSame('first', $firstValue->state());
        $this->assertSame('second', $secondValue->state());
        $this->assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function test_fingerprint_and_json_do_not_mutate_internal_json_serializable_snapshot(): void
    {
        $source = new PebbleMutableJsonValue();
        $pebble = Pebble::from(['value' => $source]);

        $this->assertSame(0, $source->calls);
        $this->assertSame(0, $pebble->get('value')->calls);
        $fingerprint = $pebble->fingerprint();

        $firstJson = $pebble->json();
        $secondJson = $pebble->json();

        $this->assertSame($firstJson, $secondJson);
        $this->assertSame($fingerprint, $pebble->fingerprint());
        $this->assertSame(0, $pebble->get('value')->calls);
    }

    public function test_get_returns_missing_default_with_original_identity(): void
    {
        $default = new stdClass();
        $pebble = Pebble::from([]);

        $this->assertSame($default, $pebble->get('missing', $default));
    }
}

class PebblePrivateStateParent
{
    public function __construct(private string $state) {}

    public function state(): string
    {
        return $this->state;
    }
}

final class PebblePrivateStateChild extends PebblePrivateStateParent {}

final class PebbleArrayObjectChild extends ArrayObject {}

final class PebbleMutableJsonValue implements JsonSerializable
{
    public int $calls = 0;

    /** @return array{calls: int} */
    public function jsonSerialize(): array
    {
        return ['calls' => ++$this->calls];
    }
}

enum PebbleFirstStatus: string
{
    case Ready = 'ready';
}

enum PebbleSecondStatus: string
{
    case Ready = 'ready';
}

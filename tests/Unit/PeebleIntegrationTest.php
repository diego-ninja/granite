<?php

namespace Tests\Unit;

use Ninja\Granite\Granite;
use Ninja\Granite\Pebble;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Pebble::class)]
class PeebleIntegrationTest extends TestCase
{
    public function test_can_create_from_granite_object(): void
    {
        $user = TestUserGranite::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $peeble = Pebble::from($user);

        $this->assertEquals(1, $peeble->id);
        $this->assertEquals('John Doe', $peeble->name);
        $this->assertEquals('john@example.com', $peeble->email);
    }

    public function test_peeble_is_independent_from_granite_validation(): void
    {
        // This would fail Granite validation but works with Pebble
        $invalidData = [
            'id' => null,
            'name' => '', // Granite might require this
            'email' => 'not-an-email', // Granite might validate this
        ];

        // Pebble doesn't care about validation
        $peeble = Pebble::from($invalidData);

        $this->assertNull($peeble->id);
        $this->assertEquals('', $peeble->name);
        $this->assertEquals('not-an-email', $peeble->email);
    }

    public function test_can_create_peeble_from_eloquent_like_model(): void
    {
        $model = new FakeEloquentModel();
        $model->id = 1;
        $model->name = 'Test User';
        $model->email = 'test@example.com';
        $model->created_at = '2024-01-01 00:00:00';

        $peeble = Pebble::from($model);

        $this->assertEquals(1, $peeble->id);
        $this->assertEquals('Test User', $peeble->name);
        $this->assertEquals('test@example.com', $peeble->email);
        $this->assertEquals('2024-01-01 00:00:00', $peeble->created_at);
    }

    public function test_can_create_snapshot_for_comparison(): void
    {
        $model = new FakeEloquentModel();
        $model->id = 1;
        $model->name = 'Original';
        $model->email = 'original@example.com';

        // Create immutable snapshot
        $snapshot = Pebble::from($model);

        // Modify model
        $model->name = 'Modified';
        $model->email = 'modified@example.com';

        // Snapshot remains unchanged
        $this->assertEquals('Original', $snapshot->name);
        $this->assertEquals('original@example.com', $snapshot->email);

        // Model has new values
        $this->assertEquals('Modified', $model->name);
        $this->assertEquals('modified@example.com', $model->email);
    }

    public function test_performance_comparison_with_granite(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Performance Test',
            'email' => 'perf@example.com',
        ];

        // Pebble should be faster as it skips validation
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            Pebble::from($data);
        }
        $peebleTime = microtime(true) - $start;

        // This is just to ensure Pebble works and is reasonably fast
        // Not a strict performance test but ensures no major regressions
        $this->assertLessThan(1.0, $peebleTime, 'Pebble creation should be fast');
    }

    public function test_can_use_with_cache(): void
    {
        $user = TestUserGranite::from([
            'id' => 1,
            'name' => 'Cached User',
            'email' => 'cached@example.com',
        ]);

        // Create immutable snapshot for caching
        $cached = Pebble::from($user);

        // Serialize for cache
        $serialized = serialize($cached->array());

        // Unserialize from cache
        $unserialized = unserialize($serialized);

        // Recreate Pebble
        $fromCache = Pebble::from($unserialized);

        $this->assertEquals($cached->array(), $fromCache->array());
    }

    public function test_handles_stdclass_objects(): void
    {
        $stdClass = new stdClass();
        $stdClass->id = 123;
        $stdClass->title = 'Test Object';
        $stdClass->active = true;

        $peeble = Pebble::from($stdClass);

        $this->assertEquals(123, $peeble->id);
        $this->assertEquals('Test Object', $peeble->title);
        $this->assertTrue($peeble->active);
    }

    public function test_can_chain_transformations(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'secret',
            'internal_id' => 'xyz',
        ];

        $peeble = Pebble::from($data)
            ->except(['password', 'internal_id'])  // Remove sensitive data
            ->merge(['status' => 'active']);       // Add new data

        $this->assertEquals(1, $peeble->id);
        $this->assertEquals('Test', $peeble->name);
        $this->assertEquals('test@example.com', $peeble->email);
        $this->assertNull($peeble->password);
        $this->assertNull($peeble->internal_id);
        $this->assertEquals('active', $peeble->status);
    }

    public function test_useful_for_api_responses(): void
    {
        // Simulate a complex model with relations
        $user = new FakeEloquentModel();
        $user->id = 1;
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->password = 'hashed_password'; // Should not be exposed

        // Create DTO for API response, excluding password
        $apiResponse = Pebble::from($user)->except(['password']);

        $json = $apiResponse->json();
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('email', $decoded);
        $this->assertArrayNotHasKey('password', $decoded);
    }

    public function test_can_create_multiple_variants(): void
    {
        $user = TestUserGranite::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Create different views of the same data
        $publicView = Pebble::from($user)->only(['id', 'name']);
        $privateView = Pebble::from($user); // All data
        $enrichedView = Pebble::from($user)->merge(['role' => 'admin']);

        $this->assertEquals(2, $publicView->count());
        $this->assertEquals(3, $privateView->count());
        $this->assertEquals(4, $enrichedView->count());
        $this->assertEquals('admin', $enrichedView->role);
    }
}

// Test helper classes
final readonly class TestUserGranite extends Granite
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
    ) {}
}

class FakeEloquentModel
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

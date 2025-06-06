<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping\Cache;

use Ninja\Granite\Mapping\Cache\SharedMappingCache;
// TypeMapping and InMemoryMappingStorage are not directly used by SharedMappingCache tests
// as it stores array configurations.
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;

#[CoversClass(SharedMappingCache::class)]
class SharedMappingCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        SharedMappingCache::resetInstanceForTesting();
        parent::tearDown();
    }

    #[Test]
    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = SharedMappingCache::getInstance();
        $instance2 = SharedMappingCache::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function test_put_get_has_on_shared_instance(): void
    {
        $cache = SharedMappingCache::getInstance();
        $configS1 = ['type' => 'S1', 'data' => 'config for S1'];

        $cache->put('SourceS1', 'DestS1', $configS1);

        $this->assertTrue($cache->has('SourceS1', 'DestS1'));
        $retrieved = $cache->get('SourceS1', 'DestS1');

        // SharedMappingCache (via InMemoryMappingCache) stores the array config directly
        $this->assertEquals($configS1, $retrieved);

        $this->assertFalse($cache->has('NonExistent', 'Pair'));
        $this->assertNull($cache->get('NonExistent', 'Pair'));
    }

    #[Test]
    public function test_clear_empties_cache(): void
    {
        $cache = SharedMappingCache::getInstance();
        $configS2 = ['type' => 'S2', 'data' => 'config for S2'];

        $cache->put('SourceS2', 'DestS2', $configS2);
        $this->assertTrue($cache->has('SourceS2', 'DestS2'));

        $cache->clear();
        $this->assertFalse($cache->has('SourceS2', 'DestS2'));
    }

    #[Test]
    public function test_get_stats_tracks_hits_misses_and_count(): void
    {
        $cache = SharedMappingCache::getInstance();
        // resetInstanceForTesting in tearDown should handle clean state,
        // but explicit clear here ensures stats are from this test's operations.
        $cache->clear();

        $statsInitial = $cache->getStats();
        $this->assertEquals(0, $statsInitial['hits']);
        $this->assertEquals(0, $statsInitial['misses']);
        // 'count' is not directly part of SharedMappingCache stats, it's from InMemoryMappingCache.
        // SharedMappingCache::getStats returns hits, misses, total, hit_rate.
        // To check count, we'd need to inspect the internal InMemoryMappingCache, which is not ideal for this test.
        // Let's focus on what SharedMappingCache::getStats() provides.
        // The underlying InMemoryMappingCache count can be inferred if needed but not directly asserted via getStats().

        $cache->get('SourceS3', 'DestS3'); // Miss
        $statsAfterMiss = $cache->getStats();
        $this->assertEquals(1, $statsAfterMiss['misses']);
        $this->assertEquals(0, $statsAfterMiss['hits']);

        $configS3 = ['type' => 'S3', 'data' => 'config for S3'];
        $cache->put('SourceS3', 'DestS3', $configS3);
        // After a put, the 'count' in the underlying InMemoryCache would be 1.
        // SharedMappingCache::getStats() doesn't directly expose this 'count'.
        // The number of items in cache is not part of its public stats API.

        $retrieved = $cache->get('SourceS3', 'DestS3'); // Hit
        $this->assertEquals($configS3, $retrieved);
        $statsAfterHit = $cache->getStats();
        $this->assertEquals(1, $statsAfterHit['hits']);
        $this->assertEquals(1, $statsAfterHit['misses']); // Misses persist from previous calls

        $cache->clear();
        $statsAfterClear = $cache->getStats();
        $this->assertEquals(0, $statsAfterClear['hits']);
        $this->assertEquals(0, $statsAfterClear['misses']);
    }
}

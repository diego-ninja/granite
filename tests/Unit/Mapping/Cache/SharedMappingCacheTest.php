<?php

namespace Tests\Unit\Mapping\Cache;

use Ninja\Granite\Mapping\Cache\SharedMappingCache;
use Tests\Helpers\TestCase;

class SharedMappingCacheTest extends TestCase
{
    private SharedMappingCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = SharedMappingCache::getInstance();
        $this->cache->clear(); // Reset cache between tests
    }

    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = SharedMappingCache::getInstance();
        $instance2 = SharedMappingCache::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_has_returns_false_for_non_existing_mapping(): void
    {
        $this->assertFalse($this->cache->has('Source', 'Destination'));
    }

    public function test_has_returns_true_for_existing_mapping(): void
    {
        $config = ['source' => 'test'];
        $this->cache->put('Source', 'Destination', $config);

        $this->assertTrue($this->cache->has('Source', 'Destination'));
    }

    public function test_get_returns_null_for_non_existing_mapping(): void
    {
        $result = $this->cache->get('Source', 'Destination');

        $this->assertNull($result);
    }

    public function test_get_returns_config_for_existing_mapping(): void
    {
        $config = ['source' => 'test', 'transformer' => null];
        $this->cache->put('Source', 'Destination', $config);

        $result = $this->cache->get('Source', 'Destination');

        $this->assertEquals($config, $result);
    }

    public function test_put_stores_mapping_config(): void
    {
        $config = ['source' => 'test', 'transformer' => 'callback'];
        $this->cache->put('Source', 'Destination', $config);

        $this->assertTrue($this->cache->has('Source', 'Destination'));
        $this->assertEquals($config, $this->cache->get('Source', 'Destination'));
    }

    public function test_put_overwrites_existing_mapping(): void
    {
        $config1 = ['source' => 'test1'];
        $config2 = ['source' => 'test2'];

        $this->cache->put('Source', 'Destination', $config1);
        $this->cache->put('Source', 'Destination', $config2);

        $result = $this->cache->get('Source', 'Destination');
        $this->assertEquals($config2, $result);
    }

    public function test_clear_removes_all_mappings(): void
    {
        $this->cache->put('Source1', 'Destination1', ['test' => 1]);
        $this->cache->put('Source2', 'Destination2', ['test' => 2]);

        $this->assertTrue($this->cache->has('Source1', 'Destination1'));
        $this->assertTrue($this->cache->has('Source2', 'Destination2'));

        $this->cache->clear();

        $this->assertFalse($this->cache->has('Source1', 'Destination1'));
        $this->assertFalse($this->cache->has('Source2', 'Destination2'));
    }

    public function test_get_stats_tracks_hits_and_misses(): void
    {
        // Initial stats
        $stats = $this->cache->getStats();
        $initialHits = $stats['hits'];
        $initialMisses = $stats['misses'];

        // Generate a miss
        $this->cache->get('NonExistent', 'Mapping');

        // Generate a hit
        $this->cache->put('Source', 'Destination', ['test' => true]);
        $this->cache->get('Source', 'Destination');

        $stats = $this->cache->getStats();

        $this->assertEquals($initialHits + 1, $stats['hits']);
        $this->assertEquals($initialMisses + 1, $stats['misses']);
    }

    public function test_get_stats_includes_required_keys(): void
    {
        $this->cache->put('Source1', 'Destination1', ['test' => 1]);
        $this->cache->put('Source2', 'Destination2', ['test' => 2]);

        $stats = $this->cache->getStats();

        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
    }

    public function test_get_stats_calculates_hit_rate(): void
    {
        // Generate some hits and misses
        $this->cache->put('Source', 'Destination', ['test' => true]);

        $this->cache->get('Source', 'Destination'); // hit
        $this->cache->get('Source', 'Destination'); // hit
        $this->cache->get('NonExistent', 'Mapping'); // miss

        $stats = $this->cache->getStats();

        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(3, $stats['total']);

        // Hit rate should be (2/3)*100 = 66.67%
        $this->assertEquals('66.67%', $stats['hit_rate']);
    }

    public function test_get_stats_handles_zero_requests(): void
    {
        $stats = $this->cache->getStats();

        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals('0%', $stats['hit_rate']);
    }

    public function test_multiple_different_mappings(): void
    {
        $config1 = ['source' => 'property1'];
        $config2 = ['source' => 'property2'];
        $config3 = ['source' => 'property3'];

        $this->cache->put('User', 'UserDTO', $config1);
        $this->cache->put('Product', 'ProductDTO', $config2);
        $this->cache->put('Order', 'OrderDTO', $config3);

        $this->assertEquals($config1, $this->cache->get('User', 'UserDTO'));
        $this->assertEquals($config2, $this->cache->get('Product', 'ProductDTO'));
        $this->assertEquals($config3, $this->cache->get('Order', 'OrderDTO'));

        $stats = $this->cache->getStats();
        $this->assertEquals(3, $stats['hits']);
    }

    public function test_singleton_maintains_state_across_instances(): void
    {
        $cache1 = SharedMappingCache::getInstance();
        $cache1->put('Source', 'Destination', ['test' => 'value']);

        $cache2 = SharedMappingCache::getInstance();

        $this->assertTrue($cache2->has('Source', 'Destination'));
        $this->assertEquals(['test' => 'value'], $cache2->get('Source', 'Destination'));
    }
}

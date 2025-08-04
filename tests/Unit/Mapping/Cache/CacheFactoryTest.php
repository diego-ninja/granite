<?php

namespace Tests\Unit\Mapping\Cache;

use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Mapping\Cache\CacheFactory;
use Ninja\Granite\Mapping\Cache\InMemoryMappingCache;
use Ninja\Granite\Mapping\Cache\PersistentMappingCache;
use Ninja\Granite\Mapping\Cache\SharedMappingCache;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Tests\Helpers\TestCase;

class CacheFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset cache directory to default
        CacheFactory::setCacheDirectory('');
    }

    public function test_create_memory_cache_by_default(): void
    {
        $cache = CacheFactory::create();

        $this->assertInstanceOf(InMemoryMappingCache::class, $cache);
        $this->assertInstanceOf(MappingCache::class, $cache);
    }

    public function test_create_memory_cache_explicitly(): void
    {
        $cache = CacheFactory::create(CacheType::Memory);

        $this->assertInstanceOf(InMemoryMappingCache::class, $cache);
    }

    public function test_create_shared_cache(): void
    {
        $cache = CacheFactory::create(CacheType::Shared);

        $this->assertInstanceOf(SharedMappingCache::class, $cache);
    }

    public function test_create_persistent_cache(): void
    {
        $cache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(PersistentMappingCache::class, $cache);
    }

    public function test_create_shared_cache_returns_singleton(): void
    {
        $cache1 = CacheFactory::create(CacheType::Shared);
        $cache2 = CacheFactory::create(CacheType::Shared);

        $this->assertSame($cache1, $cache2);
    }

    public function test_create_memory_cache_returns_new_instances(): void
    {
        $cache1 = CacheFactory::create(CacheType::Memory);
        $cache2 = CacheFactory::create(CacheType::Memory);

        $this->assertNotSame($cache1, $cache2);
        $this->assertEquals(get_class($cache1), get_class($cache2));
    }

    public function test_create_persistent_cache_returns_new_instances(): void
    {
        $cache1 = CacheFactory::create(CacheType::Persistent);
        $cache2 = CacheFactory::create(CacheType::Persistent);

        $this->assertNotSame($cache1, $cache2);
        $this->assertEquals(get_class($cache1), get_class($cache2));
    }

    public function test_set_cache_directory(): void
    {
        $testDir = '/tmp/test_cache';
        CacheFactory::setCacheDirectory($testDir);

        // Create persistent cache to use the directory
        $cache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(PersistentMappingCache::class, $cache);
    }

    public function test_set_cache_directory_removes_trailing_slashes(): void
    {
        $testDir = '/tmp/test_cache/';
        CacheFactory::setCacheDirectory($testDir);

        // Create persistent cache - should work without double slashes
        $cache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(PersistentMappingCache::class, $cache);
    }

    public function test_set_cache_directory_removes_trailing_backslashes(): void
    {
        $testDir = 'C:\\tmp\\test_cache\\';
        CacheFactory::setCacheDirectory($testDir);

        // Create persistent cache - should work without double backslashes
        $cache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(PersistentMappingCache::class, $cache);
    }

    public function test_persistent_cache_uses_temp_dir_by_default(): void
    {
        // Don't set cache directory, should use system temp
        $cache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(PersistentMappingCache::class, $cache);
    }

    public function test_all_cache_types_implement_mapping_cache(): void
    {
        $memoryCache = CacheFactory::create(CacheType::Memory);
        $sharedCache = CacheFactory::create(CacheType::Shared);
        $persistentCache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(MappingCache::class, $memoryCache);
        $this->assertInstanceOf(MappingCache::class, $sharedCache);
        $this->assertInstanceOf(MappingCache::class, $persistentCache);
    }

    public function test_cache_directory_empty_string(): void
    {
        CacheFactory::setCacheDirectory('');

        // Should fall back to system temp directory
        $cache = CacheFactory::create(CacheType::Persistent);

        $this->assertInstanceOf(PersistentMappingCache::class, $cache);
    }

    public function test_multiple_cache_directory_changes(): void
    {
        // Change directory multiple times
        CacheFactory::setCacheDirectory('/tmp/dir1');
        $cache1 = CacheFactory::create(CacheType::Persistent);

        CacheFactory::setCacheDirectory('/tmp/dir2');
        $cache2 = CacheFactory::create(CacheType::Persistent);

        // Both should be persistent caches but potentially different configs
        $this->assertInstanceOf(PersistentMappingCache::class, $cache1);
        $this->assertInstanceOf(PersistentMappingCache::class, $cache2);
    }
}

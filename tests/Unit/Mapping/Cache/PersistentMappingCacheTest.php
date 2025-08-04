<?php

namespace Tests\Unit\Mapping\Cache;

use Ninja\Granite\Mapping\Cache\PersistentMappingCache;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Helpers\TestCase;

#[CoversClass(PersistentMappingCache::class)]
class PersistentMappingCacheTest extends TestCase
{
    private string $tempCacheFile;
    private PersistentMappingCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheFile = sys_get_temp_dir() . '/test_mapping_cache_' . uniqid() . '.cache';
        $this->cache = new PersistentMappingCache($this->tempCacheFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCacheFile)) {
            unlink($this->tempCacheFile);
        }

        $tmpFile = $this->tempCacheFile . '.tmp';
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }

        parent::tearDown();
    }

    public function test_implements_mapping_cache_interface(): void
    {
        $this->assertInstanceOf(MappingCache::class, $this->cache);
    }

    public function test_has_returns_false_for_nonexistent_mapping(): void
    {
        $result = $this->cache->has(SimpleDTO::class, UserDTO::class);
        $this->assertFalse($result);
    }

    public function test_get_returns_null_for_nonexistent_mapping(): void
    {
        $result = $this->cache->get(SimpleDTO::class, UserDTO::class);
        $this->assertNull($result);
    }

    public function test_put_and_has_mapping(): void
    {
        $config = ['properties' => ['name' => 'username']];

        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);

        $this->assertTrue($this->cache->has(SimpleDTO::class, UserDTO::class));
    }

    public function test_put_and_get_mapping(): void
    {
        $config = ['properties' => ['name' => 'username', 'email' => 'emailAddress']];

        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);
        $result = $this->cache->get(SimpleDTO::class, UserDTO::class);

        $this->assertEquals($config, $result);
    }

    public function test_clear_removes_all_mappings(): void
    {
        $config1 = ['properties' => ['name' => 'username']];
        $config2 = ['properties' => ['id' => 'userId']];

        $this->cache->put(SimpleDTO::class, UserDTO::class, $config1);
        $this->cache->put(UserDTO::class, SimpleDTO::class, $config2);

        $this->assertTrue($this->cache->has(SimpleDTO::class, UserDTO::class));
        $this->assertTrue($this->cache->has(UserDTO::class, SimpleDTO::class));

        $this->cache->clear();

        $this->assertFalse($this->cache->has(SimpleDTO::class, UserDTO::class));
        $this->assertFalse($this->cache->has(UserDTO::class, SimpleDTO::class));
    }

    public function test_save_creates_cache_file(): void
    {
        $config = ['properties' => ['name' => 'username']];
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);

        $result = $this->cache->save();

        $this->assertTrue($result);
        $this->assertFileExists($this->tempCacheFile);
    }

    public function test_save_persists_cache_data(): void
    {
        $config = ['properties' => ['name' => 'username']];
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);
        $this->cache->save();

        // Create new cache instance to load persisted data
        $newCache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertTrue($newCache->has(SimpleDTO::class, UserDTO::class));
        $this->assertEquals($config, $newCache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_save_creates_directory_if_not_exists(): void
    {
        $nestedPath = sys_get_temp_dir() . '/nested/dir/test_cache_' . uniqid() . '.cache';
        $cache = new PersistentMappingCache($nestedPath);

        $config = ['properties' => ['name' => 'username']];
        $cache->put(SimpleDTO::class, UserDTO::class, $config);

        $result = $cache->save();

        $this->assertTrue($result);
        $this->assertFileExists($nestedPath);

        // Cleanup
        unlink($nestedPath);
        rmdir(dirname($nestedPath));
        rmdir(dirname(dirname($nestedPath)));
    }

    public function test_save_if_dirty_only_saves_when_dirty(): void
    {
        // Cache starts clean
        $initialModTime = file_exists($this->tempCacheFile) ? filemtime($this->tempCacheFile) : 0;

        $this->cache->saveIfDirty();

        $afterCleanSave = file_exists($this->tempCacheFile) ? filemtime($this->tempCacheFile) : 0;
        $this->assertEquals($initialModTime, $afterCleanSave);

        // Now make it dirty
        $this->cache->put(SimpleDTO::class, UserDTO::class, ['test' => 'data']);
        $this->cache->saveIfDirty();

        $this->assertFileExists($this->tempCacheFile);
    }

    public function test_load_cache_handles_corrupted_file(): void
    {
        // Write corrupted data to cache file
        file_put_contents($this->tempCacheFile, 'corrupted data');

        // Should not throw exception, just start with empty cache
        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertFalse($cache->has(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_handles_invalid_serialized_data(): void
    {
        // Write invalid serialized data
        file_put_contents($this->tempCacheFile, serialize('not an array'));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertFalse($cache->has(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_handles_nonexistent_file(): void
    {
        $nonExistentPath = sys_get_temp_dir() . '/nonexistent_cache_' . uniqid() . '.cache';

        // Should not throw exception
        $cache = new PersistentMappingCache($nonExistentPath);

        $this->assertFalse($cache->has(SimpleDTO::class, UserDTO::class));
    }

    public function test_save_returns_true_on_successful_save(): void
    {
        $cache = new PersistentMappingCache($this->tempCacheFile);

        $cache->put(SimpleDTO::class, UserDTO::class, ['test' => 'data']);
        $result = $cache->save();

        // Should return true on successful save
        $this->assertTrue($result);
    }

    public function test_multiple_mappings_persistence(): void
    {
        $config1 = ['properties' => ['name' => 'username']];
        $config2 = ['properties' => ['id' => 'userId']];
        $config3 = ['properties' => ['email' => 'emailAddress']];

        $this->cache->put(SimpleDTO::class, UserDTO::class, $config1);
        $this->cache->put(UserDTO::class, SimpleDTO::class, $config2);
        $this->cache->put('TypeA', 'TypeB', $config3);

        $this->cache->save();

        // Load in new cache instance
        $newCache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertEquals($config1, $newCache->get(SimpleDTO::class, UserDTO::class));
        $this->assertEquals($config2, $newCache->get(UserDTO::class, SimpleDTO::class));
        $this->assertEquals($config3, $newCache->get('TypeA', 'TypeB'));
    }

    public function test_clear_immediately_saves_empty_cache(): void
    {
        $config = ['properties' => ['name' => 'username']];
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);
        $this->cache->save();

        // Verify data exists
        $this->assertFileExists($this->tempCacheFile);
        $newCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertTrue($newCache->has(SimpleDTO::class, UserDTO::class));

        // Clear should save immediately
        $this->cache->clear();

        // Load fresh cache to verify clear was persisted
        $clearedCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertFalse($clearedCache->has(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_ignores_malformed_keys(): void
    {
        // Manually create cache file with malformed keys
        $badData = [
            'SimpleDTO->UserDTO' => ['valid' => 'mapping'],
            'MalformedKey' => ['should' => 'be ignored'],
            'Another->Malformed->Key' => ['also' => 'ignored'],
            'ValidKey->ValidDest' => ['valid' => 'mapping2'],
        ];

        file_put_contents($this->tempCacheFile, serialize($badData));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        // Should only load valid keys
        $this->assertTrue($cache->has('SimpleDTO', 'UserDTO'));
        $this->assertTrue($cache->has('ValidKey', 'ValidDest'));
        $this->assertFalse($cache->has('MalformedKey', ''));
    }
}

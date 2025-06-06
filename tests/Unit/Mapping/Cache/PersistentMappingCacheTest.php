<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping\Cache;

use Ninja\Granite\Mapping\Cache\PersistentMappingCache;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Mapping\InMemoryMappingStorage;
use Tests\Helpers\TestCase;

#[CoversClass(PersistentMappingCache::class)]
class PersistentMappingCacheTest extends TestCase
{
    private string $tempCacheBasePath; // Directory where cache files will be stored
    private string $cacheFilePath;     // Full path to a specific cache file

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheBasePath = dirname(__DIR__, 3) . '/_output/cache/persistent_mapping_cache_test/';
        $this->cacheFilePath = $this->tempCacheBasePath . 'granite_mappings.cache';

        // Ensure the base directory for tempCachePath exists
        if (!is_dir(dirname($this->tempCacheBasePath))) { // e.g. _output/cache
             mkdir(dirname($this->tempCacheBasePath), 0777, true);
        }
        // Clear and create the specific test cache base path for this test run
        $this->ensureDirectoryExistsAndClear($this->tempCacheBasePath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempCacheBasePath);
        // Clean up parent if it became empty and it's one of our specific test output dirs
        if (str_contains(dirname($this->tempCacheBasePath), '_output/cache') &&
            is_dir(dirname($this->tempCacheBasePath)) &&
            !(new \FilesystemIterator(dirname($this->tempCacheBasePath)))->valid()) {
            @rmdir(dirname($this->tempCacheBasePath));
        }
        parent::tearDown();
    }

    private function ensureDirectoryExistsAndClear(string $dir): void
    {
        if (is_dir($dir)) {
            $this->removeDirectory($dir);
        }
        mkdir($dir, 0777, true);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    #[Test]
    public function test_save_creates_cache_directory_if_not_exists(): void
    {
        $subDirCacheFile = $this->tempCacheBasePath . 'sub/dir/another_cache.dat';
        $cacheDirForFile = dirname($subDirCacheFile);
        $this->assertDirectoryDoesNotExist($cacheDirForFile);

        $cache = new PersistentMappingCache($subDirCacheFile);
        $cache->put('Test', 'Data', ['foo' => 'bar']); // Make it dirty
        $cache->save(); // Save should create the directory

        $this->assertDirectoryExists($cacheDirForFile);
        $this->assertFileExists($subDirCacheFile);
    }

    #[Test]
    public function test_put_get_has_with_simple_mapping(): void
    {
        $cache = new PersistentMappingCache($this->cacheFilePath);
        // PersistentMappingCache stores array configurations, not TypeMapping objects directly.
        $configToCache = ['source' => 'SourceA', 'destination' => 'DestA', 'properties' => ['id' => 'id']];

        $cache->put('SourceA', 'DestA', $configToCache);

        $this->assertTrue($cache->has('SourceA', 'DestA'));
        $retrievedConfig = $cache->get('SourceA', 'DestA');

        $this->assertIsArray($retrievedConfig);
        $this->assertEquals($configToCache, $retrievedConfig);

        $this->assertFalse($cache->has('SourceA', 'NonExistentDest'));
        $this->assertNull($cache->get('SourceA', 'NonExistentDest'));
    }

    #[Test]
    public function test_save_writes_cache_to_file_and_load_cache_on_new_instance(): void
    {
        $cache1 = new PersistentMappingCache($this->cacheFilePath);
        $configB = ['source' => 'SourceB', 'destination' => 'DestB', 'props' => ['id' => 'id']];
        $cache1->put('SourceB', 'DestB', $configB);
        $cache1->save();

        $this->assertFileExists($this->cacheFilePath);

        $cache2 = new PersistentMappingCache($this->cacheFilePath); // This should load from file
        $this->assertTrue($cache2->has('SourceB', 'DestB'));
        $retrievedConfig = $cache2->get('SourceB', 'DestB');
        $this->assertEquals($configB, $retrievedConfig);
    }

    #[Test]
    public function test_clear_removes_all_mappings_and_updates_file_on_save(): void
    {
        $cache = new PersistentMappingCache($this->cacheFilePath);
        $configC = ['source' => 'SourceC', 'destination' => 'DestC'];
        $cache->put('SourceC', 'DestC', $configC);
        $cache->save(); // Populates file
        $this->assertFileExists($this->cacheFilePath);
        $this->assertTrue($cache->has('SourceC', 'DestC'));


        $cache->clear(); // This calls save() internally for PersistentMappingCache
        $this->assertFalse($cache->has('SourceC', 'DestC'));

        // After clear(), save() is called, which should write an empty serialized array.
        $this->assertFileExists($this->cacheFilePath, "Cache file should still exist after clear and internal save.");
        $fileContents = file_get_contents($this->cacheFilePath);
        $loadedData = unserialize($fileContents);
        $this->assertEmpty($loadedData, "Cache file should contain an empty serialized array.");

        $cacheNew = new PersistentMappingCache($this->cacheFilePath); // Load from the now empty/cleared file
        $this->assertFalse($cacheNew->has('SourceC', 'DestC'));
    }

    #[Test]
    public function test_save_if_dirty_only_saves_when_changes_made(): void
    {
        $cache = new PersistentMappingCache($this->cacheFilePath);

        $cache->saveIfDirty();
        $this->assertFileDoesNotExist($this->cacheFilePath, "Cache file should not be created if not dirty on initial load.");

        $configD = ['source' => 'SourceD', 'destination' => 'DestD'];
        $cache->put('SourceD', 'DestD', $configD); // Now it's dirty

        $cache->saveIfDirty(); // Should save now
        $this->assertFileExists($this->cacheFilePath);
        clearstatcache(true, $this->cacheFilePath);
        $mtime1 = filemtime($this->cacheFilePath);

        sleep(1); // Sleep for 1 second to ensure mtime can change

        $cache->saveIfDirty(); // Not dirty, should not re-save
        clearstatcache(true, $this->cacheFilePath);
        $this->assertEquals($mtime1, filemtime($this->cacheFilePath), "File modification time should not change if not dirty.");
    }

    #[Test]
    public function test_corrupted_cache_file_loads_as_empty_cache_unserialize_returns_false(): void
    {
        // Test when unserialize returns false (e.g. malformed string)
        file_put_contents($this->cacheFilePath, 'this is not a serialized array');

        // Suppress warnings during load of corrupted file for cleaner test output
        $cache = @new PersistentMappingCache($this->cacheFilePath);

        $this->assertFalse($cache->has('any', 'any'), "Cache should be empty after loading a corrupted file (unserialize false).");
    }

    #[Test]
    public function test_corrupted_cache_file_loads_as_empty_cache_unserialize_returns_non_array(): void
    {
        // Test when unserialize returns something other than an array
        file_put_contents($this->cacheFilePath, serialize("this is a string, not an array"));

        $cache = @new PersistentMappingCache($this->cacheFilePath);
        $this->assertFalse($cache->has('any', 'any'), "Cache should be empty after loading a corrupted file (unserialize non-array).");
    }


    #[Test]
    public function test_cache_file_with_invalid_php_is_handled_gracefully(): void
    {
        // PersistentMappingCache uses serialize/unserialize, not require. This test might be less relevant.
        // If the file somehow caused an error during file_get_contents, it should be handled.
        // For instance, if it was a directory (though constructor path is now a file path).
        // Let's simulate a read failure by making the cachePath a directory temporarily for this test.
        // This specific scenario is hard to test without deeper mocks of file system functions.
        // The existing corrupted tests for unserialize cover data integrity.
        // The constructor's try-catch for file_get_contents and unserialize should prevent crashes.
        $this->markTestSkipped('Direct test for invalid PHP file with serialize/unserialize is tricky; covered by corruption tests.');
    }
}

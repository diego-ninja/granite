<?php

namespace Tests\Unit\Mapping\Cache;

use Ninja\Granite\Mapping\Cache\PersistentMappingCache;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
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
        if (file_exists($this->tempCacheFile) || is_link($this->tempCacheFile)) {
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
        $config = $this->validMappingConfig('username');

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
        $config = $this->validMappingConfig('username');
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);

        $result = $this->cache->save();

        $this->assertTrue($result);
        $this->assertFileExists($this->tempCacheFile);
    }

    public function test_save_writes_versioned_json(): void
    {
        $config = $this->validMappingConfig('username');
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);

        $this->assertTrue($this->cache->save());

        $payload = json_decode((string) file_get_contents($this->tempCacheFile), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $payload['version']);
        $this->assertSame($config, $payload['mappings'][SimpleDTO::class . '->' . UserDTO::class]);
    }

    public function test_save_persists_cache_data(): void
    {
        $config = $this->validMappingConfig('username');
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);
        $this->cache->save();

        // Create new cache instance to load persisted data
        $newCache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertTrue($newCache->has(SimpleDTO::class, UserDTO::class));
        $this->assertEquals($config, $newCache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_existing_reader_observes_clear_from_another_cache_instance(): void
    {
        $config = $this->validMappingConfig('username');
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);
        $this->assertTrue($this->cache->save());

        $reader = new PersistentMappingCache($this->tempCacheFile);
        $clearingCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertSame($config, $reader->get(SimpleDTO::class, UserDTO::class));

        $clearingCache->clear();

        $this->assertFalse($reader->has(SimpleDTO::class, UserDTO::class));
        $this->assertNull($reader->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_existing_reader_eventually_observes_clear_from_another_process(): void
    {
        $config = $this->validMappingConfig('username');
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);
        $this->assertTrue($this->cache->save());

        $reader = new PersistentMappingCache($this->tempCacheFile);
        $this->assertSame($config, $reader->get(SimpleDTO::class, UserDTO::class));

        $payload = json_decode((string) file_get_contents($this->tempCacheFile), true, 512, JSON_THROW_ON_ERROR);
        $payload['generation']++;
        $payload['mappings'] = [];
        $replacement = $this->tempCacheFile . '.external';
        file_put_contents($replacement, json_encode($payload, JSON_THROW_ON_ERROR));
        chmod($replacement, 0600);
        rename($replacement, $this->tempCacheFile);

        usleep(120_000);

        $this->assertFalse($reader->has(SimpleDTO::class, UserDTO::class));
        $this->assertNull($reader->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_independent_writers_merge_updates_instead_of_losing_existing_mappings(): void
    {
        $firstCache = new PersistentMappingCache($this->tempCacheFile);
        $secondCache = new PersistentMappingCache($this->tempCacheFile);
        $firstConfig = $this->validMappingConfig('username');
        $secondConfig = $this->validMappingConfig('userId');

        $firstCache->put(SimpleDTO::class, UserDTO::class, $firstConfig);
        $secondCache->put(UserDTO::class, SimpleDTO::class, $secondConfig);

        $this->assertTrue($firstCache->save());
        $this->assertTrue($secondCache->save());

        $persistedCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertSame($firstConfig, $persistedCache->get(SimpleDTO::class, UserDTO::class));
        $this->assertSame($secondConfig, $persistedCache->get(UserDTO::class, SimpleDTO::class));
    }

    public function test_writer_started_before_clear_cannot_resurrect_stale_mappings(): void
    {
        $staleWriter = new PersistentMappingCache($this->tempCacheFile);
        $clearingWriter = new PersistentMappingCache($this->tempCacheFile);
        $staleWriter->put(SimpleDTO::class, UserDTO::class, $this->validMappingConfig('stale'));

        $clearingWriter->clear();
        $this->assertTrue($staleWriter->save());

        $persistedCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertNull($persistedCache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_writer_started_before_clear_can_persist_a_mapping_added_after_clear(): void
    {
        $writer = new PersistentMappingCache($this->tempCacheFile);
        $clearingWriter = new PersistentMappingCache($this->tempCacheFile);
        $config = $this->validMappingConfig('fresh');

        $clearingWriter->clear();
        $writer->put(SimpleDTO::class, UserDTO::class, $config);

        $this->assertTrue($writer->save());

        $persistedCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertSame($config, $persistedCache->get(SimpleDTO::class, UserDTO::class));
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

    public function test_load_cache_rejects_callable_transformer_and_condition_strings(): void
    {
        $payload = [
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => [
                    'name' => [
                        'source' => 'name',
                        'transformer' => 'strtoupper',
                        'condition' => 'system',
                        'default' => null,
                        'hasDefault' => false,
                        'ignore' => false,
                    ],
                ],
            ],
        ];
        file_put_contents($this->tempCacheFile, json_encode($payload, JSON_THROW_ON_ERROR));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_rejects_unknown_or_mistyped_mapping_fields(): void
    {
        $payload = [
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => [
                    'name' => [
                        'source' => 123,
                        'transformer' => null,
                        'condition' => null,
                        'default' => null,
                        'hasDefault' => 'false',
                        'ignore' => false,
                        'unexpected' => true,
                    ],
                ],
            ],
        ];
        file_put_contents($this->tempCacheFile, json_encode($payload, JSON_THROW_ON_ERROR));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_rejects_unknown_envelope_fields_and_empty_property_names(): void
    {
        $payload = [
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => [
                    '' => $this->validMappingConfig('name')['value'],
                ],
            ],
            'unexpected' => true,
        ];
        file_put_contents($this->tempCacheFile, json_encode($payload, JSON_THROW_ON_ERROR));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_rejects_symlinked_cache_file(): void
    {
        $target = $this->tempCacheFile . '.target';
        $payload = [
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => $this->validMappingConfig('name'),
            ],
        ];
        file_put_contents($target, json_encode($payload, JSON_THROW_ON_ERROR));
        symlink($target, $this->tempCacheFile);

        try {
            $cache = new PersistentMappingCache($this->tempCacheFile);
            $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
        } finally {
            unlink($target);
        }
    }

    public function test_load_cache_rejects_group_or_world_writable_file(): void
    {
        $payload = [
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => $this->validMappingConfig('name'),
            ],
        ];
        file_put_contents($this->tempCacheFile, json_encode($payload, JSON_THROW_ON_ERROR));
        chmod($this->tempCacheFile, 0666);

        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_rejects_cache_reached_through_symlinked_ancestor(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || ! function_exists('symlink')) {
            $this->markTestSkipped('Symbolic links are not portable on this platform.');
        }

        $root = sys_get_temp_dir() . '/granite_cache_ancestor_' . uniqid();
        $targetDirectory = $root . '/target';
        $linkedDirectory = $root . '/linked';
        $targetFile = $targetDirectory . '/mapping-cache.json';
        mkdir($targetDirectory, 0700, true);
        file_put_contents($targetFile, json_encode([
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => $this->validMappingConfig('name'),
            ],
        ], JSON_THROW_ON_ERROR));
        chmod($targetFile, 0600);
        symlink($targetDirectory, $linkedDirectory);

        $cleanup = static function () use ($root, $targetDirectory, $linkedDirectory, $targetFile): void {
            if (is_file($targetFile)) {
                unlink($targetFile);
            }
            if (is_link($linkedDirectory)) {
                unlink($linkedDirectory);
            }
            if (is_dir($targetDirectory)) {
                rmdir($targetDirectory);
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        };

        try {
            $cache = new PersistentMappingCache($linkedDirectory . '/mapping-cache.json');
            register_shutdown_function($cleanup);

            $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
        } finally {
            $cleanup();
        }
    }

    public function test_load_cache_rejects_sticky_writable_directory_below_temp_root(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX directory permissions are required.');
        }

        $directory = sys_get_temp_dir() . '/granite_cache_sticky_' . uniqid();
        $cacheFile = $directory . '/mapping-cache.json';
        mkdir($directory, 0700);
        chmod($directory, 01777);
        file_put_contents($cacheFile, json_encode([
            'version' => 2,
            'mappings' => [
                SimpleDTO::class . '->' . UserDTO::class => $this->validMappingConfig('name'),
            ],
        ], JSON_THROW_ON_ERROR));
        chmod($cacheFile, 0600);

        try {
            $cache = new PersistentMappingCache($cacheFile);

            $this->assertNull($cache->get(SimpleDTO::class, UserDTO::class));
        } finally {
            unlink($cacheFile);
            rmdir($directory);
        }
    }

    public function test_load_cache_handles_invalid_serialized_data(): void
    {
        // Write invalid serialized data
        file_put_contents($this->tempCacheFile, serialize('not an array'));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        $this->assertFalse($cache->has(SimpleDTO::class, UserDTO::class));
    }

    public function test_load_cache_treats_serialized_object_payload_as_miss_without_warning(): void
    {
        file_put_contents(
            $this->tempCacheFile,
            serialize([SimpleDTO::class . '->' . UserDTO::class => ['transformer' => new stdClass()]]),
        );

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
        $config1 = $this->validMappingConfig('username');
        $config2 = $this->validMappingConfig('userId');
        $config3 = $this->validMappingConfig('emailAddress');

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

    public function test_save_skips_non_persistable_entries(): void
    {
        $safeConfig = $this->validMappingConfig('username');
        $unsafeConfig = ['transformer' => fn(string $value): string => $value];

        $this->cache->put(SimpleDTO::class, UserDTO::class, $safeConfig);
        $this->cache->put('UnsafeSource', 'UnsafeDestination', $unsafeConfig);

        $this->assertTrue($this->cache->save());

        $newCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertSame($safeConfig, $newCache->get(SimpleDTO::class, UserDTO::class));
        $this->assertNull($newCache->get('UnsafeSource', 'UnsafeDestination'));
        $this->assertSame($unsafeConfig, $this->cache->get('UnsafeSource', 'UnsafeDestination'));
    }

    public function test_save_does_not_write_callable_string_configuration(): void
    {
        $config = [
            'name' => [
                'source' => 'name',
                'transformer' => 'strtoupper',
                'condition' => null,
                'default' => null,
                'hasDefault' => false,
                'ignore' => false,
            ],
        ];
        $this->cache->put(SimpleDTO::class, UserDTO::class, $config);

        $this->assertTrue($this->cache->save());

        $payload = json_decode((string) file_get_contents($this->tempCacheFile), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey(SimpleDTO::class . '->' . UserDTO::class, $payload['mappings']);
        $this->assertSame($config, $this->cache->get(SimpleDTO::class, UserDTO::class));
    }

    public function test_save_skips_binary_and_non_finite_defaults_without_losing_safe_entries(): void
    {
        $safeConfig = $this->validMappingConfig('username');
        $unsafeDefaults = [
            'BinarySource' => "\xB1",
            'NanSource' => NAN,
            'PositiveInfinitySource' => INF,
            'NegativeInfinitySource' => -INF,
        ];

        $this->cache->put(SimpleDTO::class, UserDTO::class, $safeConfig);
        foreach ($unsafeDefaults as $sourceType => $default) {
            $this->cache->put($sourceType, UserDTO::class, $this->validMappingConfig('value', $default, true));
        }

        $this->assertTrue($this->cache->save());

        $persistedCache = new PersistentMappingCache($this->tempCacheFile);
        $this->assertSame($safeConfig, $persistedCache->get(SimpleDTO::class, UserDTO::class));
        foreach (array_keys($unsafeDefaults) as $sourceType) {
            $this->assertNull($persistedCache->get($sourceType, UserDTO::class));
        }
    }

    public function test_save_returns_false_when_directory_cannot_be_created(): void
    {
        $blockingFile = sys_get_temp_dir() . '/mapping_cache_blocker_' . uniqid();
        file_put_contents($blockingFile, 'file');
        $cache = new PersistentMappingCache($blockingFile . '/cache.json');
        $cache->put(SimpleDTO::class, UserDTO::class, ['safe' => true]);

        try {
            $this->assertFalse($cache->save());
        } finally {
            unlink($blockingFile);
        }
    }

    public function test_save_returns_false_and_cleans_temp_file_when_rename_fails(): void
    {
        $targetDirectory = sys_get_temp_dir() . '/mapping_cache_target_' . uniqid();
        mkdir($targetDirectory);
        $cache = new PersistentMappingCache($targetDirectory);
        $cache->put(SimpleDTO::class, UserDTO::class, ['safe' => true]);

        try {
            $this->assertFalse($cache->save());
            $this->assertSame([], glob(dirname($targetDirectory) . '/' . basename($targetDirectory) . '.tmp-*'));
        } finally {
            rmdir($targetDirectory);
        }
    }

    public function test_clear_immediately_saves_empty_cache(): void
    {
        $config = $this->validMappingConfig('username');
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
        $payload = [
            'version' => 2,
            'mappings' => [
                'SimpleDTO->UserDTO' => $this->validMappingConfig('mapping'),
                'MalformedKey' => $this->validMappingConfig('be ignored'),
                'Another->Malformed->Key' => $this->validMappingConfig('ignored'),
                'ValidKey->ValidDest' => $this->validMappingConfig('mapping2'),
            ],
        ];

        file_put_contents($this->tempCacheFile, json_encode($payload, JSON_THROW_ON_ERROR));

        $cache = new PersistentMappingCache($this->tempCacheFile);

        // Should only load valid keys
        $this->assertTrue($cache->has('SimpleDTO', 'UserDTO'));
        $this->assertTrue($cache->has('ValidKey', 'ValidDest'));
        $this->assertFalse($cache->has('MalformedKey', ''));
    }

    /** @return array<string, array<string, mixed>> */
    private function validMappingConfig(string $source, mixed $default = null, bool $hasDefault = false): array
    {
        return [
            'value' => [
                'source' => $source,
                'transformer' => null,
                'condition' => null,
                'default' => $default,
                'hasDefault' => $hasDefault,
                'ignore' => false,
            ],
        ];
    }
}

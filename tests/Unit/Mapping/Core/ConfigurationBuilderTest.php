<?php

namespace Tests\Unit\Mapping\Core;

use DateTimeInterface;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Mapping\Cache\InMemoryMappingCache;
use Ninja\Granite\Mapping\Cache\PersistentMappingCache;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Core\ConfigurationBuilder;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Helpers\TestCase;

class ConfigurationBuilderTest extends TestCase
{
    private ConfigurationBuilder $builder;
    private MappingCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new InMemoryMappingCache();
        $this->builder = new ConfigurationBuilder($this->cache);
    }

    public function test_constructor_creates_instance(): void
    {
        $builder = new ConfigurationBuilder($this->cache);
        $this->assertInstanceOf(ConfigurationBuilder::class, $builder);
    }

    public function test_constructor_with_conventions_enabled(): void
    {
        $builder = new ConfigurationBuilder($this->cache, true, 0.9);
        $this->assertInstanceOf(ConfigurationBuilder::class, $builder);
    }

    public function test_get_configuration_returns_array(): void
    {
        $source = new TestSourceClass('John', 30);
        $config = $this->builder->getConfiguration($source, TestDestinationClass::class);

        $this->assertIsArray($config);
    }

    public function test_get_configuration_uses_cache(): void
    {
        $sourceType = TestSourceClass::class;
        $destinationType = TestDestinationClass::class;

        // Pre-populate cache
        $expectedConfig = ['name' => ['source' => 'name'], 'age' => ['source' => 'age']];
        $this->cache->put($sourceType, $destinationType, $expectedConfig);

        $config = $this->builder->getConfiguration(new TestSourceClass(), $destinationType);

        $this->assertEquals($expectedConfig, $config);
    }

    public function test_get_configuration_with_array_source(): void
    {
        $source = ['name' => 'John', 'age' => 30];
        $config = $this->builder->getConfiguration($source, TestDestinationClass::class);

        $this->assertIsArray($config);
    }

    public function test_add_profile(): void
    {
        $profile = new TestMappingProfile();
        $this->builder->addProfile($profile);

        // Profile should be used in configuration building
        $config = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertIsArray($config);
    }

    public function test_late_profile_mutation_invalidates_cached_configuration(): void
    {
        $profile = new MutableTestMappingProfile();
        $this->builder->addProfile($profile);

        $initialConfig = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertSame('name', $initialConfig['name']['source']);

        $profile->mapNameFrom('age');

        $updatedConfig = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertSame('age', $updatedConfig['name']['source']);
    }

    public function test_persistent_cache_isolated_by_explicit_mapping_configuration(): void
    {
        $cachePath = sys_get_temp_dir() . '/granite_config_fingerprint_' . uniqid() . '.json';

        try {
            $firstCache = new PersistentMappingCache($cachePath);
            $firstBuilder = new ConfigurationBuilder($firstCache);
            $firstBuilder->addPropertyMapping(
                TestSourceClass::class,
                TestDestinationClass::class,
                'name',
                (new PropertyMapping())->mapFrom('name'),
            );
            $this->assertSame(
                'name',
                $firstBuilder->getConfiguration(new TestSourceClass(), TestDestinationClass::class)['name']['source'],
            );
            $this->assertTrue($firstCache->save());

            $secondBuilder = new ConfigurationBuilder(new PersistentMappingCache($cachePath));
            $secondBuilder->addPropertyMapping(
                TestSourceClass::class,
                TestDestinationClass::class,
                'name',
                (new PropertyMapping())->mapFrom('age'),
            );

            $this->assertSame(
                'age',
                $secondBuilder->getConfiguration(new TestSourceClass(), TestDestinationClass::class)['name']['source'],
            );
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }

    public function test_mutating_registered_property_mapping_invalidates_cached_configuration(): void
    {
        $mapping = (new PropertyMapping())->mapFrom('name');
        $this->builder->addPropertyMapping(
            TestSourceClass::class,
            TestDestinationClass::class,
            'name',
            $mapping,
        );

        $initialConfig = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertSame('name', $initialConfig['name']['source']);

        $mapping->mapFrom('age');

        $updatedConfig = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertSame('age', $updatedConfig['name']['source']);
    }

    public function test_replacing_one_registration_keeps_shared_mapping_mutation_observed(): void
    {
        $sharedMapping = (new PropertyMapping())->mapFrom('name');
        $this->builder->addPropertyMapping(
            TestSourceClass::class,
            TestDestinationClass::class,
            'name',
            $sharedMapping,
        );
        $this->builder->addPropertyMapping(
            TestSourceClass::class,
            TestDestinationClass::class,
            'age',
            $sharedMapping,
        );
        $this->builder->addPropertyMapping(
            TestSourceClass::class,
            TestDestinationClass::class,
            'name',
            (new PropertyMapping())->mapFrom('age'),
        );

        $initial = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertSame('name', $initial['age']['source']);

        $sharedMapping->mapFrom('age');

        $updated = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertSame('age', $updated['age']['source']);
    }

    public function test_mutating_registered_convention_invalidates_configuration_and_discovery_caches(): void
    {
        $convention = new MutablePrefixTestConvention();
        $builder = new ConfigurationBuilder(
            new InMemoryMappingCache(),
            useConventions: true,
            conventionThreshold: 1.0,
            conventions: [$convention],
        );

        $initial = $builder->getConfiguration(
            new MutableConventionSource(),
            MutableConventionDestination::class,
        );
        $this->assertSame('legacy_name', $initial['name']['source']);

        $convention->stripLegacyPrefix = false;

        $updated = $builder->getConfiguration(
            new MutableConventionSource(),
            MutableConventionDestination::class,
        );
        $this->assertSame('name', $updated['name']['source']);
    }

    public function test_mutating_nested_convention_state_invalidates_configuration_and_discovery_caches(): void
    {
        $convention = new NestedStateTestConvention();
        $builder = new ConfigurationBuilder(
            new InMemoryMappingCache(),
            useConventions: true,
            conventionThreshold: 1.0,
            conventions: [$convention],
        );

        $initial = $builder->getConfiguration(
            new MutableConventionSource(),
            MutableConventionDestination::class,
        );
        $this->assertSame('legacy_name', $initial['name']['source']);

        $convention->settings->stripLegacyPrefix = false;

        $updated = $builder->getConfiguration(
            new MutableConventionSource(),
            MutableConventionDestination::class,
        );
        $this->assertSame('name', $updated['name']['source']);
    }

    public function test_global_carbon_configuration_change_invalidates_cached_mapping(): void
    {
        GraniteConfig::reset();
        $builder = new ConfigurationBuilder(new InMemoryMappingCache());

        try {
            $initial = $builder->getConfiguration([], CarbonConfigurationDestination::class);
            $this->assertNull($initial['createdAt']['transformer']);

            GraniteConfig::getInstance()->preferCarbon();

            $updated = $builder->getConfiguration([], CarbonConfigurationDestination::class);
            $this->assertInstanceOf(CarbonTransformer::class, $updated['createdAt']['transformer']);
        } finally {
            GraniteConfig::reset();
        }
    }

    public function test_cached_configuration_revision_check_does_not_scan_every_property_mapping(): void
    {
        for ($index = 0; $index < 64; $index++) {
            $this->builder->addPropertyMapping(
                TestSourceClass::class,
                TestDestinationClass::class,
                'property_' . $index,
                (new RevisionCountingPropertyMapping())->mapFrom('name'),
            );
        }

        $source = new TestSourceClass();
        $this->builder->getConfiguration($source, TestDestinationClass::class);
        RevisionCountingPropertyMapping::$revisionReads = 0;

        $this->builder->getConfiguration($source, TestDestinationClass::class);
        $this->builder->getConfiguration($source, TestDestinationClass::class);
        $this->builder->getConfiguration($source, TestDestinationClass::class);

        $this->assertLessThanOrEqual(3, RevisionCountingPropertyMapping::$revisionReads);
    }

    public function test_mapping_compiler_fingerprint_includes_reflection_cache(): void
    {
        $fingerprintProperty = new ReflectionProperty(ConfigurationBuilder::class, 'compilerFingerprint');
        $previousFingerprint = $fingerprintProperty->getValue();
        $fingerprintProperty->setValue(null, null);

        try {
            $method = new ReflectionMethod(ConfigurationBuilder::class, 'mappingCompilerFingerprint');
            $actualFingerprint = $method->invoke($this->builder);

            $builderFile = (new ReflectionClass(ConfigurationBuilder::class))->getFileName();
            $reflectionCacheFile = (new ReflectionClass(ReflectionCache::class))->getFileName();
            $this->assertIsString($builderFile);
            $this->assertIsString($reflectionCacheFile);

            $mappingDirectory = dirname($builderFile, 2);
            $patterns = [
                $mappingDirectory . '/*.php',
                $mappingDirectory . '/*/*.php',
                dirname($mappingDirectory) . '/Serialization/Attributes/*.php',
                dirname($mappingDirectory) . '/Serialization/Carbon*.php',
                dirname($mappingDirectory) . '/Transformers/*.php',
            ];
            $files = [$reflectionCacheFile];
            foreach ($patterns as $pattern) {
                $matchedFiles = glob($pattern);
                if (false !== $matchedFiles) {
                    $files = [...$files, ...$matchedFiles];
                }
            }
            $files = array_values(array_unique($files));
            sort($files);

            $hashes = [];
            foreach ($files as $file) {
                $hashes[$file] = hash_file('sha256', $file) ?: '';
            }
            $expectedFingerprint = hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));

            $this->assertSame($expectedFingerprint, $actualFingerprint);
        } finally {
            $fingerprintProperty->setValue(null, $previousFingerprint);
        }
    }

    public function test_binary_default_is_safe_for_fingerprinting_and_remains_memory_only(): void
    {
        $cachePath = sys_get_temp_dir() . '/granite_binary_fingerprint_' . uniqid() . '.json';
        $cache = new PersistentMappingCache($cachePath);
        $builder = new ConfigurationBuilder($cache);
        $binaryDefault = "\xB1";
        $builder->addPropertyMapping(
            TestSourceClass::class,
            TestDestinationClass::class,
            'name',
            (new PropertyMapping())->mapFrom('name')->defaultValue($binaryDefault),
        );

        try {
            $config = $builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);

            $this->assertSame($binaryDefault, $config['name']['default']);
            $this->assertTrue($cache->save());

            $payload = json_decode((string) file_get_contents($cachePath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([], $payload['mappings']);
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }

    public function test_warmup_cache(): void
    {
        $profiles = [new TestMappingProfile()];
        $this->builder->warmupCache($profiles);

        // This should not throw any errors
        $this->assertTrue(true);
    }

    public function test_mapping_profile_exposes_configured_type_pairs(): void
    {
        $profile = new TestMappingProfile();

        $this->assertSame(
            [[TestSourceClass::class, TestDestinationClass::class]],
            $profile->configuredTypePairs(),
        );
    }

    public function test_warmup_cache_with_non_profile(): void
    {
        $profiles = ['not_a_profile', new TestMappingProfile()];
        $this->builder->warmupCache($profiles);

        // Should handle non-profile items gracefully
        $this->assertTrue(true);
    }

    public function test_enable_conventions(): void
    {
        $this->builder->enableConventions(true);

        // Should not throw errors
        $config = $this->builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertIsArray($config);
    }

    public function test_enable_conventions_when_already_has_mapper(): void
    {
        // Create builder with conventions enabled
        $builder = new ConfigurationBuilder($this->cache, true);

        // Enable again - should not create new mapper
        $builder->enableConventions(true);

        $config = $builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);
        $this->assertIsArray($config);
    }

    public function test_set_convention_threshold(): void
    {
        $this->builder->enableConventions(true);
        $this->builder->setConventionThreshold(0.9);

        // Should not throw errors
        $this->assertTrue(true);
    }

    public function test_set_convention_threshold_without_conventions(): void
    {
        // Should handle gracefully when convention mapper is null
        $this->builder->setConventionThreshold(0.9);
        $this->assertTrue(true);
    }

    public function test_register_convention(): void
    {
        $convention = new TestNamingConvention();
        $this->builder->enableConventions(true);
        $this->builder->registerConvention($convention);

        // Should not throw errors
        $this->assertTrue(true);
    }

    public function test_register_convention_without_conventions(): void
    {
        $convention = new TestNamingConvention();
        // Should handle gracefully when convention mapper is null
        $this->builder->registerConvention($convention);
        $this->assertTrue(true);
    }

    public function test_clear_cache(): void
    {
        $this->builder->enableConventions(true);
        $this->builder->clearCache();

        // Should not throw errors
        $this->assertTrue(true);
    }

    public function test_clear_cache_without_conventions(): void
    {
        // Should handle gracefully when convention mapper is null
        $this->builder->clearCache();
        $this->assertTrue(true);
    }

    public function test_create_reverse_configuration(): void
    {
        $sourceType = TestSourceClass::class;
        $destinationType = TestDestinationClass::class;
        $mockStorage = new TestMappingStorage();
        $reverseMapping = new TypeMapping($mockStorage, $destinationType, $sourceType);

        // Set up some configuration first
        $this->builder->getConfiguration(new TestSourceClass(), $destinationType);

        $this->builder->createReverseConfiguration($sourceType, $destinationType, $reverseMapping);

        // Should not throw errors
        $this->assertTrue(true);
    }

    public function test_build_configuration_with_non_existent_class(): void
    {
        $config = $this->builder->getConfiguration([], 'NonExistentClass');
        $this->assertIsArray($config);
    }

    public function test_build_configuration_caches_result(): void
    {
        $sourceType = TestSourceClass::class;
        $destinationType = TestDestinationClass::class;

        // First call should build and cache
        $config1 = $this->builder->getConfiguration(new TestSourceClass(), $destinationType);

        // Second call should use cache
        $config2 = $this->builder->getConfiguration(new TestSourceClass(), $destinationType);

        $this->assertEquals($config1, $config2);
        $this->assertTrue($this->cache->has($sourceType, $destinationType));
    }

    public function test_build_configuration_with_conventions(): void
    {
        $builder = new ConfigurationBuilder($this->cache, true, 0.8);

        $config = $builder->getConfiguration(new TestSourceClass(), TestDestinationClass::class);

        $this->assertIsArray($config);
    }

    public function test_configuration_with_empty_cache_entry(): void
    {
        $sourceType = TestSourceClass::class;
        $destinationType = TestDestinationClass::class;

        // Put empty array in cache
        $this->cache->put($sourceType, $destinationType, []);

        $config = $this->builder->getConfiguration(new TestSourceClass(), $destinationType);

        // Should return empty array
        $this->assertEquals([], $config);
    }

    public function test_add_property_mapping_invalidates_configuration_cache(): void
    {
        $sourceType = TestSourceClass::class;
        $destinationType = TestDestinationClass::class;

        $this->builder->getConfiguration(new TestSourceClass(), $destinationType);
        $this->assertTrue($this->cache->has($sourceType, $destinationType));

        $this->builder->addPropertyMapping(
            $sourceType,
            $destinationType,
            'name',
            (new PropertyMapping())->mapFrom('age'),
        );

        $config = $this->builder->getConfiguration(new TestSourceClass(), $destinationType);

        $this->assertSame('age', $config['name']['source']);
    }

    public function test_configuration_mutations_clear_mapping_and_convention_caches(): void
    {
        $builder = new ConfigurationBuilder($this->cache, true);
        $source = new TestSourceClass();
        $destination = TestDestinationClass::class;

        $builder->getConfiguration($source, $destination);
        $this->assertTrue($this->cache->has(TestSourceClass::class, $destination));

        $builder->setConventionThreshold(0.9);
        $this->assertFalse($this->cache->has(TestSourceClass::class, $destination));

        $builder->getConfiguration($source, $destination);
        $builder->registerConvention(new TestNamingConvention());
        $this->assertFalse($this->cache->has(TestSourceClass::class, $destination));

        $builder->getConfiguration($source, $destination);
        $builder->enableConventions(false);
        $this->assertFalse($this->cache->has(TestSourceClass::class, $destination));
    }
}

class TestSourceClass
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

class TestDestinationClass
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {}
}

class TestMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(TestSourceClass::class, TestDestinationClass::class)
            ->forMember('name', fn($m) => $m->mapFrom('name'));
    }
}

class MutableTestMappingProfile extends MappingProfile
{
    public function mapNameFrom(string $sourceProperty): void
    {
        $this->createMap(TestSourceClass::class, TestDestinationClass::class)
            ->forMember('name', fn($mapping) => $mapping->mapFrom($sourceProperty));
    }
    protected function configure(): void
    {
        $this->mapNameFrom('name');
    }
}

class TestNamingConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'test';
    }

    public function matches(string $name): bool
    {
        return true;
    }

    public function normalize(string $name): string
    {
        return $name;
    }

    public function denormalize(string $normalized): string
    {
        return $normalized;
    }

    public function transform(string $name): string
    {
        return $name;
    }

    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        return $sourceName === $destinationName ? 1.0 : 0.5;
    }
}

final class MutablePrefixTestConvention implements NamingConvention
{
    public bool $stripLegacyPrefix = true;

    public function getName(): string
    {
        return 'mutable-prefix-test';
    }

    public function matches(string $name): bool
    {
        return in_array($name, ['legacy_name', 'name'], true);
    }

    public function normalize(string $name): string
    {
        if ($this->stripLegacyPrefix && 'legacy_name' === $name) {
            return 'name';
        }

        return $name;
    }

    public function denormalize(string $normalized): string
    {
        return $normalized;
    }

    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        return $this->normalize($sourceName) === $this->normalize($destinationName) ? 1.0 : 0.0;
    }
}

final class MutableConventionSource
{
    public string $legacy_name = 'value';
}

final class MutableConventionDestination
{
    public string $name = '';
}

final class MutableConventionSettings
{
    public bool $stripLegacyPrefix = true;
    public ?NestedStateTestConvention $owner = null;
}

final class NestedStateTestConvention implements NamingConvention
{
    public MutableConventionSettings $settings;

    public function __construct()
    {
        $this->settings = new MutableConventionSettings();
        $this->settings->owner = $this;
    }

    public function getName(): string
    {
        return 'nested-state-test';
    }

    public function matches(string $name): bool
    {
        return in_array($name, ['legacy_name', 'name'], true);
    }

    public function normalize(string $name): string
    {
        if ($this->settings->stripLegacyPrefix && 'legacy_name' === $name) {
            return 'name';
        }

        return $name;
    }

    public function denormalize(string $normalized): string
    {
        return $normalized;
    }

    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        return $this->normalize($sourceName) === $this->normalize($destinationName) ? 1.0 : 0.0;
    }
}

final class CarbonConfigurationDestination
{
    public DateTimeInterface $createdAt;
}

final class RevisionCountingPropertyMapping extends PropertyMapping
{
    public static int $revisionReads = 0;

    public function getRevision(): int
    {
        self::$revisionReads++;

        return parent::getRevision();
    }
}

class TestMappingStorage implements MappingStorage
{
    private array $mappings = [];

    public function addPropertyMapping(string $sourceType, string $destinationType, string $property, PropertyMapping $mapping): void
    {
        $key = "{$sourceType}->{$destinationType}";
        $this->mappings[$key][$property] = $mapping;
    }

    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        $key = "{$sourceType}->{$destinationType}";
        return $this->mappings[$key][$property] ?? null;
    }

    public function getMappingsForTypes(string $sourceType, string $destinationType): array
    {
        $key = "{$sourceType}->{$destinationType}";
        return $this->mappings[$key] ?? [];
    }
}

<?php

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\Mapping\Cache\InMemoryMappingCache;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Core\ConfigurationBuilder;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
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

    public function test_warmup_cache(): void
    {
        $profiles = [new TestMappingProfile()];
        $this->builder->warmupCache($profiles);

        // This should not throw any errors
        $this->assertTrue(true);
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

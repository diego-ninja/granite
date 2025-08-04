<?php

namespace Tests\Unit\Mapping;

use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Mapping\BidirectionalTypeMapping;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Helpers\TestCase;

class ObjectMapperExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset global instance before each test
        ObjectMapper::reset();
    }

    protected function tearDown(): void
    {
        // Clean up global instance after each test
        ObjectMapper::reset();
        parent::tearDown();
    }

    // ===============================
    // Singleton Pattern Tests
    // ===============================

    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = ObjectMapper::getInstance();
        $instance2 = ObjectMapper::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ObjectMapper::class, $instance1);
    }

    public function test_configure_creates_new_global_instance(): void
    {
        ObjectMapper::configure(function (MapperConfig $config): void {
            $config->withCacheType(CacheType::Memory)
                ->withConventions(false);
        });

        $this->assertTrue(ObjectMapper::isConfigured());

        $instance = ObjectMapper::getInstance();
        $this->assertInstanceOf(ObjectMapper::class, $instance);
    }

    public function test_is_configured_returns_false_by_default(): void
    {
        $this->assertFalse(ObjectMapper::isConfigured());
    }

    public function test_is_configured_returns_true_after_configure(): void
    {
        ObjectMapper::configure(function (MapperConfig $config): void {
            // Empty configuration
        });

        $this->assertTrue(ObjectMapper::isConfigured());
    }

    public function test_reset_clears_global_instance(): void
    {
        $instance1 = ObjectMapper::getInstance();
        // After getInstance(), it should be configured

        ObjectMapper::reset();

        $this->assertFalse(ObjectMapper::isConfigured());

        $instance2 = ObjectMapper::getInstance();
        $this->assertNotSame($instance1, $instance2);
    }

    public function test_set_global_instance(): void
    {
        $customMapper = new ObjectMapper();

        ObjectMapper::setGlobalInstance($customMapper);

        $this->assertTrue(ObjectMapper::isConfigured());
        $this->assertSame($customMapper, ObjectMapper::getInstance());
    }

    // ===============================
    // Mapping Configuration Tests
    // ===============================

    public function test_create_map(): void
    {
        $mapper = new ObjectMapper();

        $typeMapping = $mapper->createMap(SimpleDTO::class, SimpleDTO::class);

        $this->assertInstanceOf(TypeMapping::class, $typeMapping);
    }

    public function test_create_map_bidirectional(): void
    {
        $mapper = new ObjectMapper();

        $bidirectionalMapping = $mapper->createMapBidirectional(SimpleDTO::class, SimpleDTO::class);

        $this->assertInstanceOf(BidirectionalTypeMapping::class, $bidirectionalMapping);
    }

    public function test_create_reverse_map(): void
    {
        $mapper = new ObjectMapper();

        $reverseMapping = $mapper->createReverseMap(SimpleDTO::class, SimpleDTO::class);

        $this->assertInstanceOf(TypeMapping::class, $reverseMapping);
    }

    // ===============================
    // Convention System Tests
    // ===============================

    public function test_use_conventions(): void
    {
        $mapper = new ObjectMapper();

        $result = $mapper->useConventions(true);

        $this->assertSame($mapper, $result);
    }

    public function test_use_conventions_disable(): void
    {
        $mapper = new ObjectMapper();

        $result = $mapper->useConventions(false);

        $this->assertSame($mapper, $result);
    }

    public function test_set_convention_threshold(): void
    {
        $mapper = new ObjectMapper();

        $result = $mapper->setConventionThreshold(0.8);

        $this->assertSame($mapper, $result);
    }

    public function test_register_convention(): void
    {
        $mapper = new ObjectMapper();
        $convention = new TestNamingConvention();

        $result = $mapper->registerConvention($convention);

        $this->assertSame($mapper, $result);
    }

    // ===============================
    // Cache Management Tests
    // ===============================

    public function test_clear_cache(): void
    {
        $mapper = new ObjectMapper();

        $result = $mapper->clearCache();

        $this->assertSame($mapper, $result);
    }

    public function test_warmup_cache(): void
    {
        $mapper = new ObjectMapper();

        $result = $mapper->warmupCache();

        $this->assertSame($mapper, $result);
    }

    public function test_get_cache(): void
    {
        $mapper = new ObjectMapper();

        $cache = $mapper->getCache();

        $this->assertInstanceOf(MappingCache::class, $cache);
    }

    public function test_warmup_cache_with_profiles(): void
    {
        $profile = new ExtendedTestMappingProfile();
        $config = MapperConfig::create()->withProfile($profile);
        $mapper = new ObjectMapper($config);

        $result = $mapper->warmupCache();

        $this->assertSame($mapper, $result);
    }

    // ===============================
    // MappingStorage Interface Tests
    // ===============================

    public function test_add_property_mapping(): void
    {
        $mapper = new ObjectMapper();
        $mapping = new PropertyMapping();

        // Should not throw any exceptions
        $mapper->addPropertyMapping(SimpleDTO::class, SimpleDTO::class, 'name', $mapping);

        $this->assertTrue(true);
    }

    public function test_get_mapping(): void
    {
        $mapper = new ObjectMapper();
        $mapping = new PropertyMapping();

        // Add a mapping first
        $mapper->addPropertyMapping(SimpleDTO::class, SimpleDTO::class, 'name', $mapping);

        $retrieved = $mapper->getMapping(SimpleDTO::class, SimpleDTO::class, 'name');

        $this->assertSame($mapping, $retrieved);
    }

    public function test_get_mapping_not_found(): void
    {
        $mapper = new ObjectMapper();

        $result = $mapper->getMapping(SimpleDTO::class, SimpleDTO::class, 'nonexistent');

        $this->assertNull($result);
    }

    public function test_get_mappings_for_types(): void
    {
        $mapper = new ObjectMapper();
        $mapping1 = new PropertyMapping();
        $mapping2 = new PropertyMapping();

        // Add mappings
        $mapper->addPropertyMapping(SimpleDTO::class, SimpleDTO::class, 'name', $mapping1);
        $mapper->addPropertyMapping(SimpleDTO::class, SimpleDTO::class, 'email', $mapping2);

        $mappings = $mapper->getMappingsForTypes(SimpleDTO::class, SimpleDTO::class);

        $this->assertIsArray($mappings);
        $this->assertCount(2, $mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertArrayHasKey('email', $mappings);
    }

    public function test_get_mappings_for_types_empty(): void
    {
        $mapper = new ObjectMapper();

        $mappings = $mapper->getMappingsForTypes(SimpleDTO::class, SimpleDTO::class);

        $this->assertIsArray($mappings);
        $this->assertEmpty($mappings);
    }

    // ===============================
    // Configuration Integration Tests
    // ===============================

    public function test_constructor_with_null_config(): void
    {
        $mapper = new ObjectMapper(null);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_constructor_with_memory_cache(): void
    {
        $config = MapperConfig::create()->withCacheType(CacheType::Memory);
        $mapper = new ObjectMapper($config);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_constructor_with_shared_cache(): void
    {
        $config = MapperConfig::create()->withCacheType(CacheType::Shared);
        $mapper = new ObjectMapper($config);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_constructor_with_persistent_cache(): void
    {
        $config = MapperConfig::create()->withCacheType(CacheType::Persistent);
        $mapper = new ObjectMapper($config);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_constructor_with_conventions_enabled(): void
    {
        $config = MapperConfig::create()->withConventions(true, 0.9);
        $mapper = new ObjectMapper($config);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_constructor_with_warmup_disabled(): void
    {
        $config = MapperConfig::create()->withoutWarmup();
        $mapper = new ObjectMapper($config);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_constructor_with_warmup_enabled(): void
    {
        $config = MapperConfig::create()->withWarmup();
        $mapper = new ObjectMapper($config);

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_add_profile_returns_self(): void
    {
        $mapper = new ObjectMapper();
        $profile = new ExtendedTestMappingProfile();

        $result = $mapper->addProfile($profile);

        $this->assertSame($mapper, $result);
    }

    public function test_register_profiles_with_non_profile_items(): void
    {
        // Test that non-profile items in profiles array are ignored
        $config = MapperConfig::create()
            ->withProfile(new ExtendedTestMappingProfile())
            ->withProfiles([
                new ExtendedTestMappingProfile(),
                'not_a_profile',  // This should be ignored
                123,              // This should be ignored
                null,              // This should be ignored
            ]);

        $mapper = new ObjectMapper($config);

        // Should not throw any errors
        $this->assertInstanceOf(ObjectMapper::class, $mapper);
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
        return strtolower($name);
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

class ExtendedTestMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(SimpleDTO::class, SimpleDTO::class)
            ->forMember('name', fn($m) => $m->mapFrom('name'));
    }
}

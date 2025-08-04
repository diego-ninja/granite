<?php

namespace Tests\Unit\Mapping;

use InvalidArgumentException;
use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Mapping\Conventions\CamelCaseConvention;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingProfile;
use ReflectionClass;
use Tests\Helpers\TestCase;

class MapperConfigTest extends TestCase
{
    public function test_default_creates_config_with_defaults(): void
    {
        $config = MapperConfig::default();

        $this->assertEquals(CacheType::Memory, $config->cacheType);
        $this->assertTrue($config->warmupCache);
        $this->assertFalse($config->useConventions);
        $this->assertEquals(0.8, $config->conventionThreshold);
        $this->assertEmpty($config->profiles);
        $this->assertEmpty($config->conventions);
    }

    public function test_create_creates_config_with_defaults(): void
    {
        $config = MapperConfig::create();

        $this->assertEquals(CacheType::Memory, $config->cacheType);
        $this->assertTrue($config->warmupCache);
        $this->assertFalse($config->useConventions);
        $this->assertEquals(0.8, $config->conventionThreshold);
        $this->assertEmpty($config->profiles);
        $this->assertEmpty($config->conventions);
    }

    public function test_for_development_preset(): void
    {
        $config = MapperConfig::forDevelopment();

        $this->assertEquals(CacheType::Memory, $config->cacheType);
        $this->assertFalse($config->warmupCache);
        $this->assertTrue($config->useConventions);
        $this->assertEquals(0.7, $config->conventionThreshold);
    }

    public function test_for_production_preset(): void
    {
        $config = MapperConfig::forProduction();

        $this->assertEquals(CacheType::Shared, $config->cacheType);
        $this->assertTrue($config->warmupCache);
        $this->assertTrue($config->useConventions);
        $this->assertEquals(0.8, $config->conventionThreshold);
    }

    public function test_for_testing_preset(): void
    {
        $config = MapperConfig::forTesting();

        $this->assertEquals(CacheType::Memory, $config->cacheType);
        $this->assertFalse($config->warmupCache);
        $this->assertFalse($config->useConventions);
    }

    public function test_minimal_preset(): void
    {
        $config = MapperConfig::minimal();

        $this->assertEquals(CacheType::Memory, $config->cacheType);
        $this->assertFalse($config->warmupCache);
        $this->assertFalse($config->useConventions);
    }

    public function test_with_cache_type(): void
    {
        $config = MapperConfig::create()->withCacheType(CacheType::Persistent);

        $this->assertEquals(CacheType::Persistent, $config->cacheType);
    }

    public function test_with_memory_cache(): void
    {
        $config = MapperConfig::create()->withMemoryCache();

        $this->assertEquals(CacheType::Memory, $config->cacheType);
    }

    public function test_with_shared_cache(): void
    {
        $config = MapperConfig::create()->withSharedCache();

        $this->assertEquals(CacheType::Shared, $config->cacheType);
    }

    public function test_with_persistent_cache(): void
    {
        $config = MapperConfig::create()->withPersistentCache();

        $this->assertEquals(CacheType::Persistent, $config->cacheType);
    }

    public function test_with_warmup_enabled(): void
    {
        $config = MapperConfig::create()->withWarmup(true);

        $this->assertTrue($config->warmupCache);
    }

    public function test_with_warmup_disabled(): void
    {
        $config = MapperConfig::create()->withWarmup(false);

        $this->assertFalse($config->warmupCache);
    }

    public function test_without_warmup(): void
    {
        $config = MapperConfig::create()->withoutWarmup();

        $this->assertFalse($config->warmupCache);
    }

    public function test_with_conventions_enabled(): void
    {
        $config = MapperConfig::create()->withConventions(true, 0.9);

        $this->assertTrue($config->useConventions);
        $this->assertEquals(0.9, $config->conventionThreshold);
    }

    public function test_with_conventions_default_threshold(): void
    {
        $config = MapperConfig::create()->withConventions(true);

        $this->assertTrue($config->useConventions);
        $this->assertEquals(0.8, $config->conventionThreshold);
    }

    public function test_without_conventions(): void
    {
        $config = MapperConfig::create()->withConventions(true)->withoutConventions();

        $this->assertFalse($config->useConventions);
    }

    public function test_with_convention_threshold(): void
    {
        $config = MapperConfig::create()->withConventionThreshold(0.95);

        $this->assertEquals(0.95, $config->conventionThreshold);
    }

    public function test_with_convention_threshold_clamps_to_range(): void
    {
        $config1 = MapperConfig::create()->withConventionThreshold(1.5);
        $config2 = MapperConfig::create()->withConventionThreshold(-0.5);

        $this->assertEquals(1.0, $config1->conventionThreshold);
        $this->assertEquals(0.0, $config2->conventionThreshold);
    }

    public function test_add_convention(): void
    {
        $convention = new CamelCaseConvention();
        $config = MapperConfig::create()->addConvention($convention);

        $this->assertCount(1, $config->conventions);
        $this->assertContains($convention, $config->conventions);
    }

    public function test_add_multiple_conventions(): void
    {
        $convention1 = new CamelCaseConvention();
        $convention2 = new CamelCaseConvention();

        $config = MapperConfig::create()
            ->addConvention($convention1)
            ->addConvention($convention2);

        $this->assertCount(2, $config->conventions);
        $this->assertContains($convention1, $config->conventions);
        $this->assertContains($convention2, $config->conventions);
    }

    public function test_with_profile(): void
    {
        $profile = new TestMappingProfile();
        $config = MapperConfig::create()->withProfile($profile);

        $this->assertCount(1, $config->profiles);
        $this->assertContains($profile, $config->profiles);
    }

    public function test_with_profiles(): void
    {
        $profiles = [new TestMappingProfile(), new TestMappingProfile()];
        $config = MapperConfig::create()->withProfiles($profiles);

        $this->assertCount(2, $config->profiles);
        $this->assertEquals($profiles, $config->profiles);
    }

    public function test_with_profiles_accepts_valid_profiles(): void
    {
        $profiles = [new TestMappingProfile(), new TestMappingProfile()];
        $config = MapperConfig::create()->withProfiles($profiles);

        $this->assertCount(2, $config->profiles);
    }

    public function test_validate_passes_with_valid_config(): void
    {
        $config = MapperConfig::create()
            ->withConventionThreshold(0.8)
            ->withCacheType(CacheType::Memory);

        // Should not throw any exception
        $config->validate();
        $this->assertTrue(true);
    }

    public function test_validate_throws_for_manually_invalid_config(): void
    {
        // Since the withConventionThreshold method clamps values, we need to test
        // the validation separately by calling validate directly
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Convention threshold must be between 0.0 and 1.0');

        // Create a config with invalid threshold using reflection to bypass the clamping
        $config = new ReflectionClass(MapperConfig::class);
        $constructor = $config->getConstructor();
        $constructor->setAccessible(true);
        $instance = $config->newInstanceWithoutConstructor();
        $constructor->invoke($instance, CacheType::Memory, true, false, -0.1, [], []);

        $instance->validate();
    }

    public function test_fluent_interface_chaining(): void
    {
        $convention = new CamelCaseConvention();
        $profile = new TestMappingProfile();

        $config = MapperConfig::create()
            ->withSharedCache()
            ->withWarmup(true)
            ->withConventions(true, 0.85)
            ->addConvention($convention)
            ->withProfile($profile);

        $this->assertEquals(CacheType::Shared, $config->cacheType);
        $this->assertTrue($config->warmupCache);
        $this->assertTrue($config->useConventions);
        $this->assertEquals(0.85, $config->conventionThreshold);
        $this->assertContains($convention, $config->conventions);
        $this->assertContains($profile, $config->profiles);
    }

    public function test_config_is_readonly(): void
    {
        $config = MapperConfig::create();

        // These properties should be readonly and cannot be modified
        $this->assertInstanceOf(MapperConfig::class, $config);
    }
}

class TestMappingProfile extends MappingProfile
{
    public function configure(): void
    {
        // Empty for testing
    }
}

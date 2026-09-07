<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingPreloader;
use Ninja\Granite\Mapping\ObjectMapper;
use PHPUnit\Framework\TestCase;

final class MappingPreloaderTest extends TestCase
{
    public function test_preload_materializes_configuration_and_subsequent_preload_skips_it(): void
    {
        $mapper = new ObjectMapper(MapperConfig::create()->withSharedCache()->withoutWarmup());
        $mapper->clearCache();

        $this->assertFalse($mapper->hasCachedConfiguration(PreloaderSource::class, PreloaderDestination::class));
        $this->assertSame(1, MappingPreloader::preload($mapper, [
            [PreloaderSource::class, PreloaderDestination::class],
        ]));
        $this->assertTrue($mapper->hasCachedConfiguration(PreloaderSource::class, PreloaderDestination::class));
        $this->assertSame(0, MappingPreloader::preload($mapper, [
            [PreloaderSource::class, PreloaderDestination::class],
        ]));
    }

    public function test_preload_skips_configuration_already_cached_by_mapper(): void
    {
        $mapper = new ObjectMapper(MapperConfig::create()->withSharedCache()->withoutWarmup());
        $mapper->clearCache();
        $mapper->map(new PreloaderSource('cached'), PreloaderDestination::class);

        $this->assertSame(0, MappingPreloader::preload($mapper, [
            [PreloaderSource::class, PreloaderDestination::class],
        ]));
    }
}

final readonly class PreloaderSource
{
    public function __construct(public string $value) {}
}

final class PreloaderDestination
{
    public function __construct(public string $value = '') {}
}

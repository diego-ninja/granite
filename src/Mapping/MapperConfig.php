<?php

namespace Ninja\Granite\Mapping;

use InvalidArgumentException;
use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Mapping\Contracts\NamingConvention;

final readonly class MapperConfig
{
    private function __construct(
        public CacheType $cacheType = CacheType::Memory,
        public bool $warmupCache = true,
        public bool $useConventions = false,
        public float $conventionThreshold = 0.8,
        public array $profiles = [],
        public array $conventions = [],
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public static function create(): self
    {
        return new self();
    }

    // =================
    // Preset Configurations
    // =================

    public static function forDevelopment(): self
    {
        return self::create()
            ->withMemoryCache()
            ->withoutWarmup()
            ->withConventions(true, 0.7);
    }

    public static function forProduction(): self
    {
        return self::create()
            ->withSharedCache()
            ->withWarmup()
            ->withConventions();
    }

    public static function forTesting(): self
    {
        return self::create()
            ->withMemoryCache()
            ->withoutWarmup()
            ->withoutConventions();
    }

    public static function minimal(): self
    {
        return self::create()
            ->withMemoryCache()
            ->withoutWarmup()
            ->withoutConventions();
    }

    // =================
    // Cache Configuration
    // =================

    public function withCacheType(CacheType $type): self
    {
        return new self(
            $type,
            $this->warmupCache,
            $this->useConventions,
            $this->conventionThreshold,
            $this->profiles,
            $this->conventions,
        );
    }

    public function withMemoryCache(): self
    {
        return $this->withCacheType(CacheType::Memory);
    }

    public function withSharedCache(): self
    {
        return $this->withCacheType(CacheType::Shared);
    }

    public function withPersistentCache(): self
    {
        return $this->withCacheType(CacheType::Persistent);
    }

    public function withWarmup(bool $enabled = true): self
    {
        return new self(
            $this->cacheType,
            $enabled,
            $this->useConventions,
            $this->conventionThreshold,
            $this->profiles,
            $this->conventions,
        );
    }

    public function withoutWarmup(): self
    {
        return $this->withWarmup(false);
    }

    // ======================
    // Convention Configuration
    // ======================

    public function withConventions(bool $enabled = true, float $threshold = 0.8): self
    {
        return new self(
            $this->cacheType,
            $this->warmupCache,
            $enabled,
            $threshold,
            $this->profiles,
            $this->conventions,
        );
    }

    public function withoutConventions(): self
    {
        return $this->withConventions(false);
    }

    public function withConventionThreshold(float $threshold): self
    {
        return new self(
            $this->cacheType,
            $this->warmupCache,
            $this->useConventions,
            max(0.0, min(1.0, $threshold)),
            $this->profiles,
            $this->conventions,
        );
    }

    public function addConvention(NamingConvention $convention): self
    {
        return new self(
            $this->cacheType,
            $this->warmupCache,
            $this->useConventions,
            $this->conventionThreshold,
            $this->profiles,
            [...$this->conventions, $convention],
        );
    }

    // ==================
    // Profile Configuration
    // ==================

    public function withProfile(MappingProfile $profile): self
    {
        return new self(
            $this->cacheType,
            $this->warmupCache,
            $this->useConventions,
            $this->conventionThreshold,
            [...$this->profiles, $profile],
            $this->conventions,
        );
    }

    public function withProfiles(array $profiles): self
    {
        return new self(
            $this->cacheType,
            $this->warmupCache,
            $this->useConventions,
            $this->conventionThreshold,
            [...$this->profiles, ...$profiles],
            $this->conventions,
        );
    }

    // ================
    // Validation
    // ================

    public function validate(): void
    {
        if ($this->conventionThreshold < 0.0 || $this->conventionThreshold > 1.0) {
            throw new InvalidArgumentException('Convention threshold must be between 0.0 and 1.0');
        }

        foreach ($this->profiles as $profile) {
            if ( ! $profile instanceof MappingProfile) {
                throw new InvalidArgumentException('All profiles must be instances of MappingProfile');
            }
        }

        foreach ($this->conventions as $convention) {
            if ( ! $convention instanceof NamingConvention) {
                throw new InvalidArgumentException('All conventions must implement NamingConvention');
            }
        }
    }
}

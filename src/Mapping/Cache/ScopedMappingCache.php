<?php
// ABOUTME: Exposes mapper cache entries through public source and destination type keys.
// ABOUTME: Translates public cache operations into configuration-isolated storage keys.

declare(strict_types=1);

namespace Ninja\Granite\Mapping\Cache;

use Closure;
use Ninja\Granite\Mapping\Contracts\MappingCache;

final readonly class ScopedMappingCache implements MappingCache
{
    /** @param Closure(string, string): string $sourceTypeResolver */
    public function __construct(
        private MappingCache $cache,
        private Closure $sourceTypeResolver,
    ) {}

    public function has(string $sourceType, string $destinationType): bool
    {
        return $this->cache->has($this->resolve($sourceType, $destinationType), $destinationType);
    }

    public function get(string $sourceType, string $destinationType): ?array
    {
        return $this->cache->get($this->resolve($sourceType, $destinationType), $destinationType);
    }

    public function put(string $sourceType, string $destinationType, array $config): void
    {
        $this->cache->put($this->resolve($sourceType, $destinationType), $destinationType, $config);
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    /** @return array<string, int|float|string> */
    public function getStats(): array
    {
        return $this->cache instanceof SharedMappingCache ? $this->cache->getStats() : [];
    }

    private function resolve(string $sourceType, string $destinationType): string
    {
        return ($this->sourceTypeResolver)($sourceType, $destinationType);
    }
}

<?php

namespace Ninja\Granite\Hydration;

use Ninja\Granite\Hydration\Contracts\Hydrator;
use Ninja\Granite\Hydration\Hydrators\ArrayHydrator;
use Ninja\Granite\Hydration\Hydrators\GetterHydrator;
use Ninja\Granite\Hydration\Hydrators\GraniteHydrator;
use Ninja\Granite\Hydration\Hydrators\JsonHydrator;
use Ninja\Granite\Hydration\Hydrators\ObjectHydrator;
use Ninja\Granite\Hydration\Hydrators\StringHydrator;
use RuntimeException;

/**
 * Factory for creating and managing hydrators.
 *
 * This factory maintains a registry of hydrators and resolves
 * the appropriate hydrator(s) for given data.
 */
class HydratorFactory
{
    private static ?self $instance = null;

    /** @var array<Hydrator> */
    private array $hydrators = [];

    /** @var bool */
    private bool $sorted = false;

    private function __construct()
    {
        $this->registerDefaultHydrators();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset the factory to its default state (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Register a custom hydrator.
     *
     * @param Hydrator $hydrator Hydrator instance to register
     * @return self For method chaining
     */
    public function register(Hydrator $hydrator): self
    {
        $this->hydrators[] = $hydrator;
        $this->sorted = false;

        return $this;
    }

    /**
     * Get all registered hydrators sorted by priority.
     *
     * @return array<Hydrator>
     */
    public function getHydrators(): array
    {
        if ( ! $this->sorted) {
            usort($this->hydrators, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
            $this->sorted = true;
        }

        return $this->hydrators;
    }

    /**
     * Resolve the appropriate hydrator for the given data.
     *
     * @param mixed $data Data to hydrate
     * @param string $targetClass Target class being hydrated
     * @return Hydrator|null The appropriate hydrator or null if none found
     */
    public function resolve(mixed $data, string $targetClass): ?Hydrator
    {
        foreach ($this->getHydrators() as $hydrator) {
            if ($hydrator->supports($data, $targetClass)) {
                return $hydrator;
            }
        }

        return null;
    }

    /**
     * Hydrate data using all applicable hydrators.
     *
     * This method uses a chain of hydrators to extract as much data as possible.
     * It's particularly useful for objects that may have both public properties
     * and getters.
     *
     * @param mixed $data Source data
     * @param string $targetClass Target class being hydrated
     * @return array Normalized data
     * @throws RuntimeException If no suitable hydrator is found
     */
    public function hydrateWith(mixed $data, string $targetClass): array
    {
        // For non-objects, use single hydrator
        if ( ! is_object($data)) {
            $hydrator = $this->resolve($data, $targetClass);

            if (null === $hydrator) {
                throw new RuntimeException(
                    sprintf('No hydrator found for data type: %s', get_debug_type($data)),
                );
            }

            return $hydrator->hydrate($data, $targetClass);
        }

        // For objects, use chain of hydrators to extract maximum data
        return $this->hydrateObjectWithChain($data, $targetClass);
    }

    /**
     * Register default hydrators.
     */
    private function registerDefaultHydrators(): void
    {
        $this->register(new GraniteHydrator());
        $this->register(new JsonHydrator());
        $this->register(new ArrayHydrator());
        $this->register(new ObjectHydrator());
        $this->register(new GetterHydrator());
        $this->register(new StringHydrator()); // Catch-all for invalid strings
    }

    /**
     * Hydrate an object using a chain of hydrators.
     *
     * This allows combining data from multiple extraction strategies:
     * 1. First, try high-priority hydrators (Granite, toArray, JsonSerializable, public props)
     * 2. Then, enrich with getter-based extraction
     *
     * @param object $data Source object
     * @param string $targetClass Target class
     * @return array Combined extracted data
     */
    private function hydrateObjectWithChain(object $data, string $targetClass): array
    {
        $extractedData = [];

        foreach ($this->getHydrators() as $hydrator) {
            if ( ! $hydrator->supports($data, $targetClass)) {
                continue;
            }

            // Special handling for GetterHydrator - pass existing data
            if ($hydrator instanceof GetterHydrator) {
                $additionalData = $hydrator->extractViaGetters($data, $extractedData, $targetClass);
                $extractedData = array_merge($extractedData, $additionalData);
            } else {
                // For other hydrators, use their result and stop chain
                $extractedData = $hydrator->hydrate($data, $targetClass);
                // Don't break - let GetterHydrator enrich the data
            }
        }

        return $extractedData;
    }
}

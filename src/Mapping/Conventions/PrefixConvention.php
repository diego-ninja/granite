<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

/**
 * Implementation for handling common prefixes in property names (e.g., get, set, is, has).
 */
class PrefixConvention implements NamingConvention
{
    /**
     * @var array<string> List of common prefixes
     */
    private array $prefixes = [
        'get',
        'set',
        'is',
        'has',
        'find',
        'fetch',
        'retrieve',
        'update',
        'create',
        'delete',
        'remove',
        'build',
        'parse',
        'format',
        'convert',
        'validate',
        'make',
    ];

    public function getName(): string
    {
        return 'prefix';
    }

    public function matches(string $name): bool
    {
        foreach ($this->prefixes as $prefix) {
            if (preg_match('/^' . $prefix . '[A-Z]/', $name)) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $name): string
    {
        foreach ($this->prefixes as $prefix) {
            if (preg_match('/^' . $prefix . '([A-Z].*)$/', $name, $matches)) {
                // Convert first letter to lowercase
                $withoutPrefix = lcfirst($matches[1]);

                // Use camelCase normalization for the rest
                $camelConvention = new CamelCaseConvention();
                return $camelConvention->normalize($withoutPrefix);
            }
        }

        return $name;
    }

    public function denormalize(string $normalized): string
    {
        // By default, we use 'get' prefix for denormalization
        $camelConvention = new CamelCaseConvention();
        $camelCase = $camelConvention->denormalize($normalized);

        return 'get' . ucfirst($camelCase);
    }

    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        // If one property uses a prefix and the other doesn't
        if ($this->matches($sourceName) && ! $this->matches($destinationName)) {
            $sourceNormalized = $this->normalize($sourceName);

            // Detect convention of destination property
            $destinationConvention = $this->detectConvention($destinationName);
            if (null !== $destinationConvention) {
                $destinationNormalized = $destinationConvention->normalize($destinationName);

                // Compare normalized forms
                return $sourceNormalized === $destinationNormalized ? 0.9 : 0.0;
            }
        }
        // Opposite case: destination with prefix, source without
        elseif ( ! $this->matches($sourceName) && $this->matches($destinationName)) {
            $destinationNormalized = $this->normalize($destinationName);

            // Detect convention of source property
            $sourceConvention = $this->detectConvention($sourceName);
            if (null !== $sourceConvention) {
                $sourceNormalized = $sourceConvention->normalize($sourceName);

                // Compare normalized forms
                return $sourceNormalized === $destinationNormalized ? 0.9 : 0.0;
            }
        }
        // Both have prefixes
        elseif ($this->matches($sourceName) && $this->matches($destinationName)) {
            $sourceNormalized = $this->normalize($sourceName);
            $destinationNormalized = $this->normalize($destinationName);

            return $sourceNormalized === $destinationNormalized ? 1.0 : 0.0;
        }

        return 0.0;
    }

    /**
     * Detects which convention a property name uses.
     */
    private function detectConvention(string $name): ?NamingConvention
    {
        $conventions = [
            new CamelCaseConvention(),
            new PascalCaseConvention(),
            new SnakeCaseConvention(),
            new KebabCaseConvention(),
        ];

        foreach ($conventions as $convention) {
            if ($convention->matches($name)) {
                return $convention;
            }
        }

        return null;
    }
}

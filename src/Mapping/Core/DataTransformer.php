<?php
// ABOUTME: Defines DataTransformer as part of the object mapping pipeline.
// ABOUTME: Owns the DataTransformer boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Transformers\CollectionTransformer;

final readonly class DataTransformer
{
    private TransformerInvoker $invoker;

    public function __construct(private ?Mapper $mapper = null, ?TransformerInvoker $invoker = null)
    {
        $this->invoker = $invoker ?? new TransformerInvoker();
    }

    /**
     * @param array<array-key, mixed> $sourceData
     * @param array<string, mixed> $mappingConfig
     * @return array<string, mixed>
     */
    public function transform(array $sourceData, array $mappingConfig, string $destinationType = 'unknown'): array
    {
        $result = [];

        foreach ($mappingConfig as $destinationProperty => $config) {
            $config = $this->normalizeMappingConfig($config);
            if (null === $config) {
                continue;
            }

            if ($config['ignore'] ?? false) {
                continue;
            }

            if ( ! $this->shouldApplyMapping($config, $sourceData)) {
                if ($config['hasDefault'] ?? false) {
                    $result[$destinationProperty] = $config['default'];
                }

                continue;
            }

            $sourceKey = $config['source'] ?? null;
            if ( ! is_string($sourceKey)) {
                continue;
            }

            $sourceValue = $this->getSourceValue($sourceData, $sourceKey);
            $transformedValue = $this->applyTransformation($sourceValue, $config, $sourceData, $destinationProperty, $destinationType);
            $result[$destinationProperty] = $this->applyDefaultValue($transformedValue, $config);
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function normalizeMappingConfig(mixed $config): ?array
    {
        if ( ! is_array($config)) {
            return null;
        }

        $normalized = [];
        foreach ($config as $key => $value) {
            if ( ! is_string($key)) {
                return null;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<array-key, mixed> $sourceData
     */
    private function shouldApplyMapping(array $config, array $sourceData): bool
    {
        $condition = $config['condition'] ?? null;
        return null === $condition || (is_callable($condition) && $condition($sourceData));
    }

    /** @param array<array-key, mixed> $sourceData */
    private function getSourceValue(array $sourceData, string $key): mixed
    {
        if (str_contains($key, '.')) {
            return $this->getNestedValue($sourceData, $key);
        }

        return $sourceData[$key] ?? null;
    }

    /** @param array<array-key, mixed> $data */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $key) {
            if ( ! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<array-key, mixed> $sourceData
     */
    private function applyTransformation(
        mixed $value,
        array $config,
        array $sourceData,
        string $propertyName,
        string $destinationType,
    ): mixed {
        $transformer = $config['transformer'] ?? null;

        if (null === $transformer) {
            return $value;
        }

        if ($transformer instanceof CollectionTransformer && null !== $this->mapper) {
            $transformer->setMapper($this->mapper);
        }

        return $this->invoker->invoke(
            $transformer,
            $value,
            $sourceData,
            'array',
            $destinationType,
            $propertyName,
        );
    }

    /** @param array<string, mixed> $config */
    private function applyDefaultValue(mixed $value, array $config): mixed
    {
        if (null !== $value || ! ($config['hasDefault'] ?? false)) {
            return $value;
        }

        return $config['default'];
    }
}

<?php

namespace Ninja\Granite\Mapping\Core;

final readonly class DataTransformer
{
    public function transform(array $sourceData, array $mappingConfig): array
    {
        $result = [];

        foreach ($mappingConfig as $destinationProperty => $config) {
            if ( ! is_array($config)) {
                continue;
            }

            if ($config['ignore'] ?? false) {
                continue;
            }

            if ( ! $this->shouldApplyMapping($config, $sourceData)) {
                continue;
            }

            $sourceKey = $config['source'] ?? null;
            if ( ! is_string($sourceKey)) {
                continue;
            }

            $sourceValue = $this->getSourceValue($sourceData, $sourceKey);
            $transformedValue = $this->applyTransformation($sourceValue, $config, $sourceData);
            $result[$destinationProperty] = $this->applyDefaultValue($transformedValue, $config);
        }

        return $result;
    }

    private function shouldApplyMapping(array $config, array $sourceData): bool
    {
        $condition = $config['condition'] ?? null;
        return null === $condition || (is_callable($condition) && $condition($sourceData));
    }

    private function getSourceValue(array $sourceData, string $key): mixed
    {
        if (str_contains($key, '.')) {
            return $this->getNestedValue($sourceData, $key);
        }

        return $sourceData[$key] ?? null;
    }

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

    private function applyTransformation(mixed $value, array $config, array $sourceData): mixed
    {
        $transformer = $config['transformer'] ?? null;

        if (null === $transformer) {
            return $value;
        }

        return match (true) {
            is_callable($transformer) => $transformer($value, $sourceData),
            is_object($transformer) && method_exists($transformer, 'transform') => $transformer->transform($value, $sourceData),
            is_array($transformer) && 2 === count($transformer) => $this->invokeArrayCallable($transformer, $value, $sourceData),
            default => $value,
        };
    }

    private function invokeArrayCallable(array $transformer, mixed $value, array $sourceData): mixed
    {
        [$class, $method] = $transformer;

        if (is_string($class) && class_exists($class)) {
            return $class::$method($value, $sourceData);
        }

        if (is_object($class)) {
            return $class->{$method}($value, $sourceData);
        }

        return $value;
    }

    private function applyDefaultValue(mixed $value, array $config): mixed
    {
        if (null !== $value || ! ($config['hasDefault'] ?? false)) {
            return $value;
        }

        return $config['default'];
    }
}

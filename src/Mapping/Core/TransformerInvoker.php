<?php

// ABOUTME: Centralizes transformer invocation across mapping execution paths.
// ABOUTME: Normalizes callable arity and reports invalid transformer configuration.

namespace Ninja\Granite\Mapping\Core;

use Closure;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use ReflectionFunction;

final class TransformerInvoker
{
    public function invoke(
        mixed $transformer,
        mixed $value,
        array $sourceData,
        string $sourceType = 'array',
        string $destinationType = 'unknown',
        string $propertyName = 'unknown',
    ): mixed {
        if ($transformer instanceof Transformer) {
            return $transformer->transform($value, $sourceData);
        }

        if ( ! is_callable($transformer)) {
            throw MappingException::transformationFailed(
                $sourceType,
                $destinationType,
                $propertyName,
                'configured transformer is not callable and does not implement Transformer',
            );
        }

        $reflection = new ReflectionFunction(Closure::fromCallable($transformer));
        $arguments = [];

        if ($reflection->isVariadic() || 0 < $reflection->getNumberOfParameters()) {
            $arguments[] = $value;
        }

        if ($reflection->isVariadic() || 1 < $reflection->getNumberOfParameters()) {
            $arguments[] = $sourceData;
        }

        return $transformer(...$arguments);
    }
}

<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Monads\Contracts\Maybe;
use Ninja\Granite\Monads\Factories\Maybe;

class MaybePropertyMapping extends PropertyMapping
{
    /**
     * Transform value returning Maybe
     */
    public function transformMaybe(mixed $value, array $sourceData = []): Maybe
    {
        // Skip if explicitly ignored
        if ($this->isIgnored()) {
            return Maybe::none();
        }

        // Check condition if set
        if ($this->getCondition() !== null && !($this->getCondition())($sourceData)) {
            return $this->hasDefaultValue()
                ? Maybe::some($this->getDefaultValue())
                : Maybe::none();
        }

        $maybe = Maybe::of($value);

        // Apply transformer if set
        if ($this->getTransformer() !== null) {
            $maybe = $maybe->map(fn($v) => $this->applyTransformer($v, $sourceData));
        }

        // Use default value if empty and default is set
        if ($maybe->isNone() && $this->hasDefaultValue()) {
            return Maybe::some($this->getDefaultValue());
        }

        return $maybe;
    }

    /**
     * Safe transformation that catches exceptions
     */
    public function safeTransform(mixed $value, array $sourceData = []): Maybe
    {
        return Maybe::fromCallable(fn() => $this->transform($value, $sourceData));
    }

    private function applyTransformer(mixed $value, array $sourceData): mixed
    {
        $transformer = $this->getTransformer();

        if (is_callable($transformer)) {
            return $transformer($value, $sourceData);
        }

        if ($transformer instanceof Transformer) {
            return $transformer->transform($value, $sourceData);
        }

        return $value;
    }
}
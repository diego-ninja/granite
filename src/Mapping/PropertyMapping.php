<?php

namespace Ninja\Granite\Mapping;

class PropertyMapping
{
    private ?string $sourceProperty = null;
    private mixed $transformer = null;
    private bool $ignore = false;

    /**
     * Map from specific source property.
     */
    public function mapFrom(string $sourceProperty): self
    {
        $this->sourceProperty = $sourceProperty;
        return $this;
    }

    /**
     * Set transformer for this property mapping.
     */
    public function using(callable|Transformer $transformer): self
    {
        $this->transformer = $transformer;
        return $this;
    }

    /**
     * Ignore this property during mapping.
     */
    public function ignore(): self
    {
        $this->ignore = true;
        return $this;
    }

    /**
     * Transform value with context.
     */
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if ($this->ignore) {
            return null;
        }

        if ($this->transformer !== null) {
            if (is_callable($this->transformer)) {
                return ($this->transformer)($value, $sourceData);
            }

            if ($this->transformer instanceof Transformer) {
                return $this->transformer->transform($value, $sourceData);
            }
        }

        return $value;
    }

    public function getSourceProperty(): ?string
    {
        return $this->sourceProperty;
    }

    public function isIgnored(): bool
    {
        return $this->ignore;
    }
}
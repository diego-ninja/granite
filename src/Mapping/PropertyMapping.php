<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Mapping\Contracts\Transformer;

class PropertyMapping
{
    private ?string $sourceProperty = null;
    private mixed $transformer = null;
    private bool $ignore = false;
    /**
     * @var callable|null Function that receives source data and returns boolean
     */
    private mixed $condition = null;
    private mixed $defaultValue = null;
    private bool $hasDefaultValue = false;

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
     * Only apply this mapping if the condition is true.
     *
     * @param callable $condition Function that receives source data and returns boolean
     * @return $this
     */
    public function onlyIf(callable $condition): self
    {
        $this->condition = $condition;
        return $this;
    }

    /**
     * @param class-string $itemType
     */
    public function asCollection(
        string $itemType,
        bool $preserveKeys = false,
        bool $recursive = false,
        mixed $itemTransformer = null,
    ): self {
        $this->transformer = new \Ninja\Granite\Transformers\CollectionTransformer(
            destinationType: $itemType,
            mapper: null,
            preserveKeys: $preserveKeys,
            recursive: $recursive,
            itemTransformer: $itemTransformer,
        );

        return $this;
    }

    /**
     * Set the default value to use when condition fails or source is null.
     *
     * @param mixed $value Default value
     * @return $this
     */
    public function defaultValue(mixed $value): self
    {
        $this->defaultValue = $value;
        $this->hasDefaultValue = true;
        return $this;
    }

    /**
     * Transform value with context.
     */
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        // Skip if explicitly ignored
        if ($this->ignore) {
            return null;
        }

        // Check condition if set
        if (null !== $this->condition && ! ($this->condition)($sourceData)) {
            return $this->hasDefaultValue ? $this->defaultValue : null;
        }

        // Apply transformer if set
        if (null !== $this->transformer) {
            if (is_callable($this->transformer)) {
                $value = ($this->transformer)($value, $sourceData);
            } elseif ($this->transformer instanceof Transformer) {
                $value = $this->transformer->transform($value, $sourceData);
            }
        }

        // Use default value if the value is null and default is set
        if (null === $value && $this->hasDefaultValue) {
            return $this->defaultValue;
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

    public function hasCondition(): bool
    {
        return null !== $this->condition;
    }

    /**
     * Get the condition callable.
     */
    public function getCondition(): mixed
    {
        return $this->condition;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    /**
     * Get the transformer.
     */
    public function getTransformer(): mixed
    {
        return $this->transformer;
    }

    /**
     * Set a reference to the mapper.
     */
    public function setMapper(mixed $mapper): self
    {
        if ($this->transformer instanceof \Ninja\Granite\Transformers\CollectionTransformer && $mapper instanceof Contracts\Mapper) {
            $this->transformer->setMapper($mapper);
        }

        return $this;
    }
}

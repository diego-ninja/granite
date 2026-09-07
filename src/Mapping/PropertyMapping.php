<?php
// ABOUTME: Defines PropertyMapping as part of the object mapping pipeline.
// ABOUTME: Owns the PropertyMapping boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping;

use Closure;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Mapping\Core\TransformerInvoker;

class PropertyMapping
{
    private int $revision = 0;
    /** @var array<int, array{listener: Closure(): void, registrations: int}> */
    private array $mutationListeners = [];
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
        $this->markChanged();
        return $this;
    }

    /**
     * Set transformer for this property mapping.
     */
    public function using(callable|Transformer $transformer): self
    {
        $this->transformer = $transformer;
        $this->markChanged();
        return $this;
    }

    /**
     * Ignore this property during mapping.
     */
    public function ignore(): self
    {
        $this->ignore = true;
        $this->markChanged();
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
        $this->markChanged();
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
        $this->markChanged();

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
        $this->markChanged();
        return $this;
    }

    /**
     * @deprecated Use the DataTransformer runtime through ObjectMapper instead.
     */
    /** @param array<array-key, mixed> $sourceData */
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
            $value = (new TransformerInvoker())->invoke($this->transformer, $value, $sourceData);
        }

        // Use default value if the value is null and default is set
        if (null === $value && $this->hasDefaultValue) {
            return $this->defaultValue;
        }

        return $value;
    }

    /**
     * @return array{source: string, transformer: mixed, condition: mixed, default: mixed, hasDefault: bool, ignore: bool}
     */
    public function toConfig(string $propertyName): array
    {
        return [
            'source' => $this->sourceProperty ?? $propertyName,
            'transformer' => $this->transformer,
            'condition' => $this->condition,
            'default' => $this->defaultValue,
            'hasDefault' => $this->hasDefaultValue,
            'ignore' => $this->ignore,
        ];
    }

    public function getSourceProperty(): ?string
    {
        return $this->sourceProperty;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    /** @internal Used by mapping owners to propagate revisions in O(1). */
    public function observeMutations(object $owner, callable $listener): void
    {
        $ownerId = spl_object_id($owner);
        if (isset($this->mutationListeners[$ownerId])) {
            $this->mutationListeners[$ownerId]['registrations']++;
            return;
        }

        $callback = Closure::fromCallable($listener);
        $this->mutationListeners[$ownerId] = [
            'listener' => static function () use ($callback): void {
                $callback();
            },
            'registrations' => 1,
        ];
    }

    /** @internal */
    public function stopObservingMutations(object $owner): void
    {
        $ownerId = spl_object_id($owner);
        if ( ! isset($this->mutationListeners[$ownerId])) {
            return;
        }

        $this->mutationListeners[$ownerId]['registrations']--;
        if (0 === $this->mutationListeners[$ownerId]['registrations']) {
            unset($this->mutationListeners[$ownerId]);
        }
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

    private function markChanged(): void
    {
        $this->revision++;
        foreach ($this->mutationListeners as $registration) {
            $registration['listener']();
        }
    }
}

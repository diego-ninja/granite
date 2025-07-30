<?php

namespace Ninja\Granite\Transformers;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\Transformer;
use RuntimeException;

final class CollectionTransformer implements Transformer
{
    /**
     * @param class-string $destinationType Target item type for collection elements
     * @param Mapper|null $mapper ObjectMapper instance
     * @param bool $preserveKeys Whether to preserve array keys
     * @param bool $recursive Whether to recursively transform nested collections
     * @param mixed $itemTransformer Optional transformer for individual items
     */
    public function __construct(
        private readonly string $destinationType,
        private ?Mapper $mapper = null,
        private readonly bool   $preserveKeys = false,
        private readonly bool   $recursive = false,
        private readonly mixed $itemTransformer = null,
    ) {}

    public function setMapper(Mapper $mapper): self
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * Transform a collection.
     */
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if (null === $value) {
            return null;
        }

        if ( ! is_array($value)) {
            return $value; // Return as is if not an array
        }

        $result = [];

        foreach ($value as $key => $item) {
            $transformedItem = $this->transformItem($item);

            if ($this->preserveKeys) {
                $result[$key] = $transformedItem;
            } else {
                $result[] = $transformedItem;
            }
        }

        return $result;
    }

    /**
     * Transform a single collection item.
     */
    private function transformItem(mixed $item): mixed
    {
        // Apply item transformer if provided
        if (null !== $this->itemTransformer) {
            if (is_callable($this->itemTransformer)) {
                return ($this->itemTransformer)($item);
            }
            if ($this->itemTransformer instanceof Transformer) {
                return $this->itemTransformer->transform($item);
            }
        }

        // Handle recursive transformation for nested arrays
        if (is_array($item) && $this->recursive) {
            // If the item is a simple associative array, map it
            if ($this->isAssociativeArray($item)) {
                if (null === $this->mapper) {
                    throw new RuntimeException('Mapper is required for object mapping');
                }
                return $this->mapper->map($item, $this->destinationType);
            }

            // If it's a collection itself, recursively transform each item
            $nestedResult = [];
            foreach ($item as $nestedKey => $nestedItem) {
                $transformedNestedItem = $this->transformItem($nestedItem);
                if ($this->preserveKeys) {
                    $nestedResult[$nestedKey] = $transformedNestedItem;
                } else {
                    $nestedResult[] = $transformedNestedItem;
                }
            }
            return $nestedResult;
        }

        // Standard mapping for non-collection items
        if (is_array($item) || is_object($item)) {
            if (null === $this->mapper) {
                throw new RuntimeException('Mapper is required for object mapping');
            }
            return $this->mapper->map($item, $this->destinationType);
        }

        return $item;
    }

    /**
     * Check if an array is associative (vs sequential).
     */
    private function isAssociativeArray(array $array): bool
    {
        // An empty array is considered associative for our purposes
        if (empty($array)) {
            return true;
        }

        // Check if array keys are sequential integers starting from 0
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionProperty;

/**
 * Configures mapping between source and destination types.
 */
final class TypeMapping
{
    /**
     * Whether the mapping is sealed.
     */
    private bool $sealed = false;

    /**
     * Constructor.
     *
     * @param MappingStorage $storage Mapping storage
     * @param string $sourceType Source type name
     * @param string $destinationType Destination type name
     */
    public function __construct(
        private readonly MappingStorage $storage,
        private readonly string $sourceType,
        private readonly string $destinationType
    ) {}

    /**
     * Configure mapping for a specific destination property.
     *
     * @param string $destinationProperty Destination property name
     * @param callable $configuration Configuration function
     * @return $this For method chaining
     * @throws MappingException If the mapping is already sealed
     */
    public function forMember(string $destinationProperty, callable $configuration): self
    {
        if ($this->sealed) {
            throw new MappingException(
                $this->sourceType,
                $this->destinationType,
                "Cannot modify mapping after it has been sealed",
                $destinationProperty
            );
        }

        $mapping = new PropertyMapping();
        $configuration($mapping);

        $this->storage->addPropertyMapping(
            $this->sourceType,
            $this->destinationType,
            $destinationProperty,
            $mapping
        );

        return $this;
    }

    /**
     * Validate all mappings and finalize the configuration.
     *
     * @return $this For method chaining
     * @throws MappingException If validation fails
     */
    public function seal(): self
    {
        if ($this->sealed) {
            return $this; // Already sealed
        }

        try {
            // Get all configured mappings for this type pair
            $mappings = $this->storage->getMappingsForTypes($this->sourceType, $this->destinationType);

            // Validate destination properties exist
            $this->validateDestinationProperties($mappings);

            // Validate source properties when possible
            if ($this->sourceType !== 'array' && class_exists($this->sourceType)) {
                $this->validateSourceProperties($mappings);
            }

            // Validate transformers and conditions
            $this->validateTransformersAndConditions($mappings);

            // Check for redundant or conflicting mappings
            $this->detectConflicts($mappings);

            // Mark as sealed
            $this->sealed = true;

            return $this;
        } catch (MappingException $e) {
            // Re-throw mapping exceptions
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions
            throw new MappingException(
                $this->sourceType,
                $this->destinationType,
                "Error while validating mapping: " . $e->getMessage(),
                null,
                0,
                $e
            );
        }
    }

    /**
     * Validate that all destination properties exist.
     *
     * @param array $mappings Property mappings to validate
     * @throws MappingException If a destination property doesn't exist
     */
    private function validateDestinationProperties(array $mappings): void
    {
        if (!class_exists($this->destinationType)) {
            throw new MappingException(
                $this->sourceType,
                $this->destinationType,
                "Destination type '{$this->destinationType}' does not exist"
            );
        }

        try {
            $destProperties = ReflectionCache::getPublicProperties($this->destinationType);
            $destPropNames = array_map(
                fn(ReflectionProperty $p) => $p->getName(),
                $destProperties
            );

            foreach (array_keys($mappings) as $propName) {
                if (!in_array($propName, $destPropNames)) {
                    throw new MappingException(
                        $this->sourceType,
                        $this->destinationType,
                        "Destination property '{$propName}' does not exist in '{$this->destinationType}'",
                        $propName
                    );
                }
            }
        } catch (ReflectionException $e) {
            throw new MappingException(
                $this->sourceType,
                $this->destinationType,
                "Error examining destination type: " . $e->getMessage()
            );
        }
    }

    /**
     * Validate that all source properties exist.
     *
     * @param array $mappings Property mappings to validate
     * @throws MappingException If a source property doesn't exist
     */
    private function validateSourceProperties(array $mappings): void
    {
        try {
            $sourceProperties = ReflectionCache::getPublicProperties($this->sourceType);
            $sourcePropNames = array_map(
                fn(ReflectionProperty $p) => $p->getName(),
                $sourceProperties
            );

            foreach ($mappings as $destProp => $mapping) {
                $sourceProp = $mapping->getSourceProperty();

                // Skip if no explicit source property or using dot notation (nested properties)
                if ($sourceProp === null || str_contains($sourceProp, '.')) {
                    continue;
                }

                if (!in_array($sourceProp, $sourcePropNames)) {
                    throw new MappingException(
                        $this->sourceType,
                        $this->destinationType,
                        "Source property '{$sourceProp}' does not exist in '{$this->sourceType}'",
                        $destProp
                    );
                }
            }
        } catch (ReflectionException $e) {
            throw new MappingException(
                $this->sourceType,
                $this->destinationType,
                "Error examining source type: " . $e->getMessage()
            );
        }
    }

    /**
     * Validate transformers and conditions.
     *
     * @param array $mappings Property mappings to validate
     * @throws MappingException If a transformer or condition is invalid
     */
    private function validateTransformersAndConditions(array $mappings): void
    {
        foreach ($mappings as $destProp => $mapping) {
            $transformer = $mapping->getTransformer();

            if ($transformer !== null && !($transformer instanceof Transformer) && !is_callable($transformer)) {
                throw new MappingException(
                    $this->sourceType,
                    $this->destinationType,
                    "Invalid transformer for property '{$destProp}': must be callable or implement Transformer",
                    $destProp
                );
            }

            // We would also validate conditions here if PropertyMapping exposed the condition
            // For now we're assuming the condition is properly checked within PropertyMapping
        }
    }

    /**
     * Detect redundant or conflicting mappings.
     *
     * @param array $mappings Property mappings to check
     * @throws MappingException If conflicts are found
     */
    private function detectConflicts(array $mappings): void
    {
        $sourceProps = [];
        $ignoredProps = [];

        // Collect source properties and ignored flags
        foreach ($mappings as $destProp => $mapping) {
            if ($mapping->isIgnored()) {
                $ignoredProps[$destProp] = true;
                continue;
            }

            $sourceProp = $mapping->getSourceProperty();
            if ($sourceProp !== null) {
                $sourceProps[$sourceProp][] = $destProp;
            }
        }

        // Check for properties that are both mapped and ignored
        foreach ($ignoredProps as $destProp => $ignored) {
            if (isset($mappings[$destProp]) && $mappings[$destProp]->getSourceProperty() !== null) {
                throw new MappingException(
                    $this->sourceType,
                    $this->destinationType,
                    "Property '{$destProp}' is both mapped and ignored",
                    $destProp
                );
            }
        }
    }

    /**
     * Check if this mapping is sealed.
     *
     * @return bool Whether the mapping is sealed
     */
    public function isSealed(): bool
    {
        return $this->sealed;
    }

    /**
     * Get the source type.
     *
     * @return string Source type name
     */
    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    /**
     * Get the destination type.
     *
     * @return string Destination type name
     */
    public function getDestinationType(): string
    {
        return $this->destinationType;
    }
}
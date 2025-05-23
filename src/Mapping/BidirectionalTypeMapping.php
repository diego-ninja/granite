<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use RuntimeException;

/**
 * Manages bidirectional mapping between two types.
 */
final class BidirectionalTypeMapping
{
    /**
     * Forward mapping (typeA to typeB).
     */
    private TypeMapping $forwardMapping;

    /**
     * Reverse mapping (typeB to typeA).
     */
    private TypeMapping $reverseMapping;

    /**
     * Forward member mappings.
     */
    private array $forwardMemberMappings = [];

    /**
     * Reverse member mappings.
     */
    private array $reverseMemberMappings = [];

    /**
     * Whether the mapping is sealed.
     */
    private bool $sealed = false;

    /**
     * Constructor.
     *
     * @param MappingStorage $storage Mapping storage
     * @param string $typeA First type name
     * @param string $typeB Second type name
     */
    public function __construct(
        private readonly MappingStorage $storage,
        private readonly string $typeA,
        private readonly string $typeB
    ) {
        // Create basic mappings in both directions
        $this->forwardMapping = new TypeMapping($storage, $typeA, $typeB);
        $this->reverseMapping = new TypeMapping($storage, $typeB, $typeA);
    }

    /**
     * Configure bidirectional mapping for a property pair.
     *
     * @param string $propertyA Property name in typeA
     * @param string $propertyB Property name in typeB
     * @return $this For method chaining
     * @throws RuntimeException If the mapping is already sealed
     */
    public function forMembers(string $propertyA, string $propertyB): self
    {
        if ($this->sealed) {
            throw new RuntimeException("Cannot modify bidirectional mapping after it has been sealed");
        }

        // Store the mapping configuration
        $this->forwardMemberMappings[$propertyA] = $propertyB;
        $this->reverseMemberMappings[$propertyB] = $propertyA;

        return $this;
    }

    /**
     * Configure multiple property pairs at once.
     *
     * @param array $propertyPairs Associative array of propertyA => propertyB pairs
     * @return $this For method chaining
     * @throws RuntimeException If the mapping is already sealed
     */
    public function forMemberPairs(array $propertyPairs): self
    {
        if ($this->sealed) {
            throw new RuntimeException("Cannot modify bidirectional mapping after it has been sealed");
        }

        foreach ($propertyPairs as $propertyA => $propertyB) {
            $this->forwardMemberMappings[$propertyA] = $propertyB;
            $this->reverseMemberMappings[$propertyB] = $propertyA;
        }

        return $this;
    }

    /**
     * Apply a one-way mapping from typeA to typeB.
     *
     * @param string $propertyB Destination property in typeB
     * @param callable $configuration Mapping configuration function
     * @return $this For method chaining
     * @throws RuntimeException|\Ninja\Granite\Mapping\Exceptions\MappingException If the mapping is already sealed
     */
    public function forForwardMember(string $propertyB, callable $configuration): self
    {
        if ($this->sealed) {
            throw new RuntimeException("Cannot modify bidirectional mapping after it has been sealed");
        }

        // Apply configuration to forward mapping only
        $this->forwardMapping->forMember($propertyB, $configuration);

        return $this;
    }

    /**
     * Apply a one-way mapping from typeB to typeA.
     *
     * @param string $propertyA Destination property in typeA
     * @param callable $configuration Mapping configuration function
     * @return $this For method chaining
     * @throws RuntimeException|\Ninja\Granite\Mapping\Exceptions\MappingException If the mapping is already sealed
     */
    public function forReverseMember(string $propertyA, callable $configuration): self
    {
        if ($this->sealed) {
            throw new RuntimeException("Cannot modify bidirectional mapping after it has been sealed");
        }

        // Apply configuration to reverse mapping only
        $this->reverseMapping->forMember($propertyA, $configuration);

        return $this;
    }

    /**
     * Validate and finalize the bidirectional mapping.
     *
     * @return $this For method chaining
     * @throws MappingException
     */
    public function seal(): self
    {
        if ($this->sealed) {
            return $this; // Already sealed
        }

        // Apply all bidirectional mappings
        foreach ($this->forwardMemberMappings as $propertyA => $propertyB) {
            $this->forwardMapping->forMember($propertyB, fn($m) => $m->mapFrom($propertyA));
            $this->reverseMapping->forMember($propertyA, fn($m) => $m->mapFrom($propertyB));
        }

        // Seal both mappings
        $this->forwardMapping->seal();
        $this->reverseMapping->seal();

        $this->sealed = true;

        return $this;
    }

    /**
     * Get the forward mapping (typeA to typeB).
     *
     * @return TypeMapping Forward mapping
     */
    public function getForwardMapping(): TypeMapping
    {
        return $this->forwardMapping;
    }

    /**
     * Get the reverse mapping (typeB to typeA).
     *
     * @return TypeMapping Reverse mapping
     */
    public function getReverseMapping(): TypeMapping
    {
        return $this->reverseMapping;
    }
}
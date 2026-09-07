<?php
// ABOUTME: Defines SerializationConvention as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the SerializationConvention boundary between metadata and serialized values.

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;
use InvalidArgumentException;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use ReflectionClass;

/**
 * Attribute to apply a naming convention to all properties in a class during serialization.
 * Properties with explicit SerializedName attributes will not be affected.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class SerializationConvention
{
    /**
     * Constructor.
     *
     * @param class-string<NamingConvention>|NamingConvention $convention The naming convention to apply
     * @param bool $bidirectional Whether to apply the convention in both directions (serialize/deserialize)
     */
    public function __construct(
        public string|NamingConvention $convention,
        public bool $bidirectional = true,
    ) {}

    /**
     * Get the convention instance.
     *
     * @return NamingConvention Convention instance
     * @throws InvalidArgumentException If the convention class doesn't exist or implement NamingConvention
     */
    public function getConvention(): NamingConvention
    {
        if ($this->convention instanceof NamingConvention) {
            return $this->convention;
        }

        if ( ! class_exists($this->convention)) {
            throw new InvalidArgumentException("Convention class '{$this->convention}' does not exist");
        }

        if ( ! is_subclass_of($this->convention, NamingConvention::class)) {
            throw new InvalidArgumentException("Convention class '{$this->convention}' must implement NamingConvention");
        }

        $reflection = new ReflectionClass($this->convention);
        $constructor = $reflection->getConstructor();
        if ( ! $reflection->isInstantiable()
            || (null !== $constructor && $constructor->getNumberOfRequiredParameters() > 0)) {
            throw new InvalidArgumentException("Convention class '{$this->convention}' must be instantiable without arguments");
        }

        return $reflection->newInstance();
    }
}

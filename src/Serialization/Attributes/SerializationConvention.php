<?php

namespace Ninja\Granite\Serialization\Attributes;

use Attribute;
use InvalidArgumentException;
use Ninja\Granite\Mapping\Contracts\NamingConvention;

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

        return new $this->convention();
    }
}

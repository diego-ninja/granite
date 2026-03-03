<?php

namespace Ninja\Granite\Serialization;

class Metadata
{
    private array $hiddenProperties;

    public function __construct(private array $propertyNames = [], array $hiddenProperties = [])
    {
        $this->hiddenProperties = empty($hiddenProperties) ? [] : array_fill_keys($hiddenProperties, true);
    }

    public function getSerializedName(string $propertyName): string
    {
        $serializedName = $this->propertyNames[$propertyName] ?? $propertyName;
        return is_string($serializedName) ? $serializedName : $propertyName;
    }

    public function isHidden(string $propertyName): bool
    {
        return isset($this->hiddenProperties[$propertyName]);
    }

    public function mapPropertyName(string $propertyName, string $serializedName): self
    {
        $this->propertyNames[$propertyName] = $serializedName;
        return $this;
    }

    public function hideProperty(string $propertyName): self
    {
        $this->hiddenProperties[$propertyName] = true;
        return $this;
    }

    public function debug(): array
    {
        return [
            'propertyNames' => $this->propertyNames,
            'hiddenProperties' => array_keys($this->hiddenProperties),
        ];
    }
}

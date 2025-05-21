<?php

namespace Ninja\Granite\Serialization;

class Metadata
{
    public function __construct(private array $propertyNames = [], private array $hiddenProperties = [])
    {
    }

    public function getSerializedName(string $propertyName): string
    {
        return $this->propertyNames[$propertyName] ?? $propertyName;
    }

    public function isHidden(string $propertyName): bool
    {
        return in_array($propertyName, $this->hiddenProperties, true);
    }

    public function mapPropertyName(string $propertyName, string $serializedName): self
    {
        $this->propertyNames[$propertyName] = $serializedName;
        return $this;
    }

    public function hideProperty(string $propertyName): self
    {
        if (!in_array($propertyName, $this->hiddenProperties, true)) {
            $this->hiddenProperties[] = $propertyName;
        }
        return $this;
    }

    // Método para depuración que puede ayudar a verificar el estado
    public function debug(): array
    {
        return [
            'propertyNames' => $this->propertyNames,
            'hiddenProperties' => $this->hiddenProperties
        ];
    }
}
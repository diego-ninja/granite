<?php
// ABOUTME: Defines Metadata as part of the serialization and date metadata pipeline.
// ABOUTME: Owns the Metadata boundary between metadata and serialized values.

namespace Ninja\Granite\Serialization;

class Metadata
{
    /** @var array<string, bool> */
    private array $hiddenProperties;

    /**
     * @param array<string, string> $propertyNames
     * @param array<int, string> $hiddenProperties
     */
    public function __construct(private array $propertyNames = [], array $hiddenProperties = [])
    {
        $this->hiddenProperties = empty($hiddenProperties) ? [] : array_fill_keys($hiddenProperties, true);
    }

    public function getSerializedName(string $propertyName): string
    {
        $serializedName = $this->propertyNames[$propertyName] ?? $propertyName;
        return $serializedName;
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

    /** @return array<string, array<string, string>|list<string>> */
    public function debug(): array
    {
        return [
            'propertyNames' => $this->propertyNames,
            'hiddenProperties' => array_keys($this->hiddenProperties),
        ];
    }
}

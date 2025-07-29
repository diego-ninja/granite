<?php

namespace Ninja\Granite\Exceptions;

use Exception;

/**
 * Exception thrown when serialization/deserialization fails.
 */
class SerializationException extends GraniteException
{
    private string $objectType;
    private string $operation;
    private ?string $propertyName;

    public function __construct(
        string $objectType,
        string $operation,
        string $message = "",
        ?string $propertyName = null,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        $this->objectType = $objectType;
        $this->operation = $operation;
        $this->propertyName = $propertyName;

        if (empty($message)) {
            $message = sprintf(
                'Serialization failed during %s for %s%s',
                $operation,
                $objectType,
                $propertyName ? " (property: {$propertyName})" : '',
            );
        }

        $context = [
            'object_type' => $objectType,
            'operation' => $operation,
            'property_name' => $propertyName,
        ];

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Create exception for unsupported type during serialization.
     */
    public static function unsupportedType(string $objectType, string $propertyName, string $valueType): self
    {
        return new self(
            $objectType,
            'serialization',
            sprintf('Cannot serialize property "%s" of type "%s"', $propertyName, $valueType),
            $propertyName,
        );
    }

    /**
     * Create exception for deserialization errors.
     */
    public static function deserializationFailed(string $objectType, string $reason, ?Exception $previous = null): self
    {
        return new self(
            $objectType,
            'deserialization',
            sprintf('Failed to deserialize %s: %s', $objectType, $reason),
            null,
            0,
            $previous,
        );
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }
}

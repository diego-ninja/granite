<?php

namespace Ninja\Granite\Mapping\Exceptions;

use Exception;
use Ninja\Granite\Exceptions\GraniteException;

/**
 * Exception thrown when ObjectMapper operations fail.
 */
class MappingException extends GraniteException
{
    private string $sourceType;
    private string $destinationType;
    private ?string $propertyName;

    public function __construct(
        string $sourceType,
        string $destinationType,
        string $message = "",
        ?string $propertyName = null,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        $this->sourceType = $sourceType;
        $this->destinationType = $destinationType;
        $this->propertyName = $propertyName;

        if (empty($message)) {
            $message = sprintf(
                'Mapping failed from %s to %s%s',
                $sourceType,
                $destinationType,
                $propertyName ? " (property: {$propertyName})" : '',
            );
        }

        $context = [
            'source_type' => $sourceType,
            'destination_type' => $destinationType,
            'property_name' => $propertyName,
        ];

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Create exception for missing destination type.
     */
    public static function destinationTypeNotFound(string $destinationType): self
    {
        return new self(
            'unknown',
            $destinationType,
            sprintf('Destination type "%s" does not exist', $destinationType),
        );
    }

    /**
     * Create exception for transformation errors.
     */
    public static function transformationFailed(
        string $sourceType,
        string $destinationType,
        string $propertyName,
        string $reason,
        ?Exception $previous = null,
    ): self {
        return new self(
            $sourceType,
            $destinationType,
            sprintf('Failed to transform property "%s": %s', $propertyName, $reason),
            $propertyName,
            0,
            $previous,
        );
    }

    /**
     * Create exception for unsupported source type.
     */
    public static function unsupportedSourceType(mixed $source): self
    {
        $sourceType = is_object($source) ? get_class($source) : gettype($source);

        return new self(
            $sourceType,
            'unknown',
            sprintf('Unsupported source type: %s', $sourceType),
        );
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getDestinationType(): string
    {
        return $this->destinationType;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }
}

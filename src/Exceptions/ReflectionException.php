<?php

namespace Ninja\Granite\Exceptions;

use Exception;

/**
 * Exception thrown when reflection operations fail.
 */
class ReflectionException extends GraniteException
{
    private string $className;
    private string $operation;

    public function __construct(
        string $className,
        string $operation,
        string $message = "",
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->className = $className;
        $this->operation = $operation;

        if (empty($message)) {
            $message = sprintf('Reflection operation "%s" failed for class %s', $operation, $className);
        }

        $context = [
            'class_name' => $className,
            'operation' => $operation
        ];

        parent::__construct($message, $code, $previous, $context);
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Create exception for class not found errors.
     */
    public static function classNotFound(string $className): static
    {
        return new static(
            $className,
            'class_loading',
            sprintf('Class "%s" not found', $className)
        );
    }

    /**
     * Create exception for property access errors.
     */
    public static function propertyAccessFailed(string $className, string $propertyName, ?Exception $previous = null): static
    {
        return new static(
            $className,
            'property_access',
            sprintf('Failed to access property "%s" in class %s', $propertyName, $className),
            0,
            $previous
        );
    }
}
<?php

namespace Ninja\Granite\Exceptions;

use Exception;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends GraniteException
{
    private array $errors;
    private string $objectType;

    public function __construct(string $objectType, array $errors, string $message = "", int $code = 0, ?Exception $previous = null)
    {
        $this->objectType = $objectType;
        $this->errors = $errors;

        if (empty($message)) {
            $message = sprintf('Validation failed for %s', $objectType);
        }

        $context = [
            'object_type' => $objectType,
            'validation_errors' => $errors
        ];

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Get validation errors by field.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasFieldErrors(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get the object type that failed validation.
     */
    public function getObjectType(): string
    {
        return $this->objectType;
    }

    /**
     * Get all error messages as a flat array.
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * Get formatted error message for display.
     */
    public function getFormattedMessage(): string
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "• {$error}";
            }
        }

        return sprintf(
            "Validation failed for %s:\n%s",
            $this->objectType,
            implode("\n", $messages)
        );
    }
}
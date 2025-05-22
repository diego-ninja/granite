<?php

namespace Ninja\Granite\Exceptions;

use Exception;

/**
 * Base exception for all Granite-related errors.
 */
class GraniteException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context information about the error.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Add context information to the exception.
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
}
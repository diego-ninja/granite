<?php

namespace Ninja\Granite\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Granite-related errors.
 */
class GraniteException extends Exception
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /** @param array<string, mixed> $context */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context information about the error.
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Add context information to the exception.
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
}

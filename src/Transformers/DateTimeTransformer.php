<?php

namespace Ninja\Granite\Transformers;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Ninja\Granite\Mapping\Contracts\Transformer;

final readonly class DateTimeTransformer implements Transformer
{
    public function __construct(
        private string $format = DateTimeInterface::ATOM,
    ) {}

    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception $e) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get the format used for parsing.
     *
     * @return string Date format
     */
    public function getFormat(): string
    {
        return $this->format;
    }
}

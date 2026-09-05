<?php

namespace Ninja\Granite\Transformers;

use DateTimeImmutable;
use DateTimeInterface;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Throwable;

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
                $result = DateTimeImmutable::createFromFormat($this->format, $value);
                $errors = DateTimeImmutable::getLastErrors();

                if (false === $result || (false !== $errors && (0 < $errors['warning_count'] || 0 < $errors['error_count']))) {
                    return null;
                }

                return $result;
            } catch (Throwable) {
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

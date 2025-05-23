<?php

namespace Ninja\Granite\Mapping\Transformers;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Ninja\Granite\Mapping\Contracts\Transformer;

final readonly class DateTimeTransformer implements Transformer
{
    public function __construct(
        private string $format = DateTimeInterface::ATOM
    ) {}

    /**
     * @throws DateMalformedStringException
     */
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($this->format);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        return $value;
    }
}
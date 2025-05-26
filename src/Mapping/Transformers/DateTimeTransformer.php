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

    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value;
    }
}
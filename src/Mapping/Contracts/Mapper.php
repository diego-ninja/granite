<?php

namespace Ninja\Granite\Mapping\Contracts;

interface Mapper
{
    /**
     * Map from source object/array to destination type.
     *
     * @template T of object
     * @param mixed $source Source data
     * @param class-string<T> $destinationType Destination class
     * @return T Mapped object
     */
    public function map(mixed $source, string $destinationType): object;

    /**
     * Map from source to an existing destination object.
     *
     * @param mixed $source Source data
     * @param object $destination Destination object to populate
     * @return object Updated destination object
     */
    public function mapTo(mixed $source, object $destination): object;

    /**
     * Map array of objects.
     *
     * @template T of object
     * @param array $source Array of source objects
     * @param class-string<T> $destinationType Destination class
     * @return T[] Array of mapped objects
     */
    public function mapArray(array $source, string $destinationType): array;
}

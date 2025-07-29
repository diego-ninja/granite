<?php

namespace Ninja\Granite\Mapping\Contracts;

use Ninja\Granite\Monads\Contracts\Either;

interface Mapper
{
    /**
     * Map from source object/array to destination type.
     *
     * @template T
     * @param mixed $source Source data
     * @param class-string<T> $destinationType Destination class
     */
    public function map(mixed $source, string $destinationType): Either;

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
     * @template T
     * @param array $sources Array of source objects
     * @param class-string<T> $destinationType Destination class
     */
    public function mapArray(array $sources, string $destinationType): Either;
}
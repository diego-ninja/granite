<?php

use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\AutoMapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;

if (!function_exists('map')) {
    /**
     * Map an object or array to a destination type using global AutoMapper instance.
     *
     * @template T
     * @param mixed $source Source data (object, array, or JSON string)
     * @param class-string<T> $destinationType Destination class name
     * @return T Mapped object
     * @throws MappingException|GraniteException If mapping fails
     *
     * @example
     * // Basic usage
     * $userDto = map($userEntity, UserDto::class);
     *
     * // From array
     * $user = map(['name' => 'John', 'email' => 'john@example.com'], User::class);
     *
     * // From JSON
     * $user = map('{"name":"John","email":"john@example.com"}', User::class);
     */
    function map(mixed $source, string $destinationType): object
    {
        return AutoMapper::getInstance()->map($source, $destinationType);
    }
}

if (!function_exists('map_array')) {
    /**
     * Map an array of objects to a destination type using global AutoMapper instance.
     *
     * @template T
     * @param array $source Array of source objects
     * @param class-string<T> $destinationType Destination class name
     * @return T[] Array of mapped objects
     * @throws MappingException|GraniteException If mapping fails
     */
    function map_array(array $source, string $destinationType): array
    {
        return AutoMapper::getInstance()->mapArray($source, $destinationType);
    }
}

if (!function_exists('map_to')) {
    /**
     * Map source data to an existing destination object.
     *
     * @param mixed $source Source data
     * @param object $destination Existing destination object
     * @return object Updated destination object
     * @throws MappingException|GraniteException If mapping fails
     */
    function map_to(mixed $source, object $destination): object
    {
        return AutoMapper::getInstance()->mapTo($source, $destination);
    }
}

if (!function_exists('configure_mapper')) {
    /**
     * Configure the global AutoMapper instance.
     *
     * @param callable $configurator Configuration callback
     *
     * @example
     * configure_mapper(function($mapper) {
     *     $mapper->addProfile(new UserMappingProfile());
     *     $mapper->setConventionConfidenceThreshold(0.9);
     * });
     */
    function configure_mapper(callable $configurator): void
    {
        AutoMapper::configure($configurator);
    }
}
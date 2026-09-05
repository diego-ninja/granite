<?php

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final readonly class ObjectFactory
{
    /**
     * @param array $data
     * @param class-string $className
     * @throws MappingException
     */
    public function create(array $data, string $className): object
    {
        try {
            if ('stdClass' === $className) {
                return (object) $data;
            }

            if (is_subclass_of($className, GraniteObject::class)) {
                return $className::from($data);
            }

            return $this->createFromReflection($data, $className);
        } catch (MappingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MappingException(
                'array',
                $className,
                'Failed to create instance: ' . $e->getMessage(),
                null,
                0,
                $e,
            );
        }
    }

    /**
     * @throws MappingException
     */
    public function populate(object $object, array $data): object
    {
        try {
            $reflection = ReflectionCache::getClass(get_class($object));

            foreach ($data as $propName => $propValue) {
                if ( ! is_string($propName) || ! $reflection->hasProperty($propName)) {
                    continue;
                }

                $property = $reflection->getProperty($propName);
                if ( ! $property->isPublic() || $property->isReadOnly()) {
                    continue;
                }

                try {
                    $property->setValue($object, $propValue);
                } catch (Throwable $e) {
                    throw MappingException::propertyHydrationFailed(
                        'array',
                        get_class($object),
                        $propName,
                        $e,
                    );
                }
            }

            return $object;
        } catch (MappingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MappingException(
                'array',
                get_class($object),
                'Failed to populate object: ' . $e->getMessage(),
                null,
                0,
                $e,
            );
        }
    }

    /**
     * @param array $data Source data
     * @param class-string $className Target class name
     * @throws MappingException
     */
    private function createFromReflection(array $data, string $className): object
    {
        try {
            $reflection = ReflectionCache::getClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor) {
                $instance = $this->createWithConstructor($reflection, $constructor, $data);
            } else {
                $instance = $reflection->newInstanceWithoutConstructor();
            }

            // Set remaining properties
            $this->setRemainingProperties($instance, $data, $reflection);

            return $instance;
        } catch (MappingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MappingException(
                'array',
                $className,
                'Failed to create instance: ' . $e->getMessage(),
                null,
                0,
                $e,
            );
        }
    }

    private function createWithConstructor(ReflectionClass $reflection, ReflectionMethod $constructor, array &$data): object
    {
        $args = [];
        $parameters = $constructor->getParameters();

        foreach ($parameters as $param) {
            $paramName = $param->getName();

            if (array_key_exists($paramName, $data)) {
                $args[] = $data[$paramName];
                unset($data[$paramName]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw MappingException::missingRequiredValue('array', $reflection->getName(), $paramName);
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    private function setRemainingProperties(object $instance, array $data, ReflectionClass $reflection): void
    {
        foreach ($data as $propName => $propValue) {
            if ( ! is_string($propName) || ! $reflection->hasProperty($propName)) {
                continue;
            }

            $property = $reflection->getProperty($propName);
            if ( ! $property->isPublic() || $property->isReadOnly()) {
                continue;
            }

            try {
                $property->setValue($instance, $propValue);
            } catch (Throwable $e) {
                throw MappingException::propertyHydrationFailed(
                    'array',
                    $reflection->getName(),
                    $propName,
                    $e,
                );
            }
        }
    }
}

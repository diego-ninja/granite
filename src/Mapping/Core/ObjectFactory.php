<?php

namespace Ninja\Granite\Mapping\Core;

use Exception;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
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
        if ('stdClass' === $className) {
            return (object) $data;
        }

        if (is_subclass_of($className, GraniteObject::class)) {
            return $className::from($data);
        }

        return $this->createFromReflection($data, $className);
    }

    /**
     * @throws MappingException
     */
    public function populate(object $object, array $data): object
    {
        try {
            $reflection = ReflectionCache::getClass(get_class($object));

            foreach ($data as $propName => $propValue) {
                if ($reflection->hasProperty($propName)) {
                    $property = $reflection->getProperty($propName);
                    if ($property->isPublic() && ! $property->isReadOnly()) {
                        $property->setValue($object, $propValue);
                    }
                }
            }

            return $object;
        } catch (Exception $e) {
            throw new MappingException('array', get_class($object), "Failed to populate object: " . $e->getMessage());
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
        } catch (Exception $e) {
            throw new MappingException('array', $className, "Failed to create instance: " . $e->getMessage());
        }
    }

    /**
     * @throws ReflectionException
     */
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
                $args[] = $this->getDefaultValueForType($param->getType());
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    private function setRemainingProperties(object $instance, array $data, ReflectionClass $reflection): void
    {
        foreach ($data as $propName => $propValue) {
            try {
                if ($reflection->hasProperty($propName)) {
                    $property = $reflection->getProperty($propName);
                    if ($property->isPublic() && ! $property->isReadOnly()) {
                        $property->setValue($instance, $propValue);
                    }
                }
            } catch (Throwable) {
                // Ignore errors when setting properties
            }
        }
    }

    private function getDefaultValueForType(?ReflectionType $type): mixed
    {
        if ( ! $type instanceof ReflectionNamedType) {
            return null;
        }

        return match ($type->getName()) {
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            'string' => '',
            'array' => [],
            default => null,
        };
    }
}

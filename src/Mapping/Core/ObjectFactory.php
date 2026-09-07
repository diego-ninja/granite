<?php
// ABOUTME: Defines ObjectFactory as part of the object mapping pipeline.
// ABOUTME: Owns the ObjectFactory boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

final readonly class ObjectFactory
{
    /**
     * @param array<array-key, mixed> $data
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
     * @param array<array-key, mixed> $data
     * @throws MappingException
     */
    public function populate(object $object, array $data): object
    {
        try {
            $reflection = ReflectionCache::getClass(get_class($object));
            $changes = $this->snapshotMutableState($reflection, $object);

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
                    $this->rollback($object, $changes);
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
     * @param list<array{property: ReflectionProperty, initialized: bool, value: mixed}> $changes
     */
    private function rollback(object $object, array $changes): void
    {
        foreach (array_reverse($changes) as $change) {
            try {
                if ($change['initialized']) {
                    $this->setRawPropertyValue($change['property'], $object, $change['value']);
                    continue;
                }

                $this->unsetProperty($change['property'], $object);
            } catch (Throwable) {
                // Preserve the original hydration failure even if best-effort rollback fails.
            }
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return list<array{property: ReflectionProperty, initialized: bool, value: mixed}>
     */
    private function snapshotMutableState(ReflectionClass $reflection, object $object): array
    {
        $state = [];
        $class = $reflection;
        while (false !== $class) {
            foreach ($class->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $class->getName()
                    || $property->isStatic()
                    || $property->isReadOnly()
                    || (PHP_VERSION_ID >= 80400 && $property->isVirtual())) {
                    continue;
                }

                $initialized = $property->isInitialized($object);
                $state[] = [
                    'property' => $property,
                    'initialized' => $initialized,
                    'value' => $initialized ? $this->getRawPropertyValue($property, $object) : null,
                ];
            }
            $class = $class->getParentClass();
        }

        return $state;
    }

    private function getRawPropertyValue(ReflectionProperty $property, object $object): mixed
    {
        if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
            return $property->getRawValue($object);
        }

        return $property->getValue($object);
    }

    private function setRawPropertyValue(ReflectionProperty $property, object $object, mixed $value): void
    {
        if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
            $property->setRawValue($object, $value);
            return;
        }

        $property->setValue($object, $value);
    }

    private function unsetProperty(ReflectionProperty $property, object $object): void
    {
        $unset = static function (object $target, string $propertyName): void {
            unset($target->{$propertyName});
        };
        $scopedUnset = $unset->bindTo(null, $property->getDeclaringClass()->getName());
        $scopedUnset($object, $property->getName());
    }

    /**
     * @param array<array-key, mixed> $data Source data
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

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<array-key, mixed> $data
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
                throw MappingException::missingRequiredValue('array', $reflection->getName(), $paramName);
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param ReflectionClass<object> $reflection
     */
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

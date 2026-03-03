<?php

// ABOUTME: Pre-computed class metadata for fast-path object creation.
// ABOUTME: Detects simple DTOs (primitive or Granite types, no attributes) to bypass the hydration pipeline.

namespace Ninja\Granite\Support;

use Error;
use Ninja\Granite\Contracts\GraniteObject;
use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

final class ClassProfile
{
    private const array BUILTIN_TYPES = ['int', 'string', 'float', 'bool', 'array', 'null'];

    /** @var array<string, mixed> Ordered map of param name => default value or REQUIRED sentinel */
    public readonly array $constructorParams;

    public readonly bool $canUseFastPath;

    /** @var array<string, class-string> Param name => Granite subclass for non-primitive params */
    public readonly array $graniteParams;

    /** @var class-string */
    private readonly string $className;

    /** @var string[] Param names in constructor order */
    private readonly array $paramNames;

    /**
     * @param class-string $className
     * @param array<string, mixed> $constructorParams
     * @param string[] $paramNames
     * @param array<string, class-string> $graniteParams
     */
    private function __construct(string $className, array $constructorParams, array $paramNames, bool $canUseFastPath, array $graniteParams = [])
    {
        $this->className = $className;
        $this->constructorParams = $constructorParams;
        $this->paramNames = $paramNames;
        $this->canUseFastPath = $canUseFastPath;
        $this->graniteParams = $graniteParams;
    }

    /**
     * @param class-string $class
     */
    public static function build(string $class): self
    {
        $reflection = ReflectionCache::getClass($class);
        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            return new self($class, [], [], false, []);
        }

        $params = [];
        /** @var array<string, class-string> $graniteParams */
        $graniteParams = [];
        $canUseFastPath = ! self::hasDisqualifyingClassAttributes($reflection)
            && ! self::hasOverriddenRules($reflection)
            && ! self::hasReadonlyParentProperties($reflection);

        foreach ($constructor->getParameters() as $param) {
            if ($canUseFastPath && ! self::isFastPathParameter($param, $graniteParams)) {
                $canUseFastPath = false;
            }

            if ($canUseFastPath && self::hasDisqualifyingPropertyAttributes($reflection, $param->getName())) {
                $canUseFastPath = false;
            }

            if ($param->isDefaultValueAvailable()) {
                $params[$param->getName()] = $param->getDefaultValue();
            } else {
                $params[$param->getName()] = self::required();
            }
        }

        // Ensure all public properties are covered by constructor params
        if ($canUseFastPath) {
            $publicProps = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
            if (count($publicProps) !== count($params)) {
                $canUseFastPath = false;
            }
        }

        if ( ! $canUseFastPath) {
            $graniteParams = [];
        }

        return new self($class, $params, array_keys($params), $canUseFastPath, $graniteParams);
    }

    /**
     * @return object|null Created instance, or null to fall back to slow path
     */
    public function tryFastPath(array $args): ?object
    {
        if (array_is_list($args)) {
            if (1 === count($args) && is_array($args[0])) {
                $data = $args[0];
            } else {
                return null;
            }
        } else {
            $data = $args;
        }

        $className = $this->className;

        // Pure-primitive DTOs: use C-level array_intersect_key + named args unpacking
        if (empty($this->graniteParams)) {
            $filtered = array_intersect_key($data, $this->constructorParams);
            if (count($filtered) !== count($this->paramNames)) {
                return null;
            }

            return new $className(...$filtered);
        }

        // DTOs with Granite-typed params: need per-param conversion
        $constructorArgs = [];
        foreach ($this->paramNames as $name) {
            if ( ! array_key_exists($name, $data)) {
                return null;
            }

            $value = $data[$name];

            if (isset($this->graniteParams[$name])) {
                if (is_array($value)) {
                    $graniteClass = $this->graniteParams[$name];
                    $value = $graniteClass::from($value);
                } elseif (null !== $value && ! $value instanceof GraniteObject) {
                    return null;
                }
            }

            $constructorArgs[] = $value;
        }

        return new $className(...$constructorArgs);
    }

    /**
     * Compare two instances by direct property access, with early exit on first difference.
     */
    public function areEqual(object $a, object $b): bool
    {
        if (empty($this->graniteParams)) {
            foreach ($this->paramNames as $name) {
                if ($a->{$name} !== $b->{$name}) {
                    return false;
                }
            }

            return true;
        }

        foreach ($this->paramNames as $name) {
            $va = $a->{$name};
            $vb = $b->{$name};

            if ($va instanceof Granite && $vb instanceof Granite) {
                if ( ! $va->equals($vb)) {
                    return false;
                }
            } elseif ($va !== $vb) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build array representation by direct property access, bypassing reflection and metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $instance): array
    {
        $result = [];
        foreach ($this->paramNames as $name) {
            try {
                $value = $instance->{$name};
            } catch (Error) {
                continue;
            }

            if (isset($this->graniteParams[$name]) && $value instanceof GraniteObject) {
                $value = $value->array();
            }

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * Check if a parameter type is compatible with the fast path.
     * Returns true for builtin types and Granite subclasses.
     * For Granite subclasses, the class name is added to $graniteParams.
     *
     * @param array<string, class-string> $graniteParams Collects Granite-typed param names
     */
    private static function isFastPathParameter(ReflectionParameter $param, array &$graniteParams): bool
    {
        $type = $param->getType();

        if (null === $type || ! $type instanceof ReflectionNamedType) {
            return false;
        }

        $typeName = $type->getName();

        if (in_array($typeName, self::BUILTIN_TYPES, true)) {
            return true;
        }

        if (is_subclass_of($typeName, GraniteObject::class)) {
            $graniteParams[$param->getName()] = $typeName;
            return true;
        }

        return false;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private static function hasDisqualifyingClassAttributes(ReflectionClass $reflection): bool
    {
        $disqualifying = [
            SerializationConvention::class,
            DateTimeProvider::class,
        ];

        foreach ($disqualifying as $attrClass) {
            if ( ! empty($reflection->getAttributes($attrClass, ReflectionAttribute::IS_INSTANCEOF))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private static function hasDisqualifyingPropertyAttributes(ReflectionClass $reflection, string $propertyName): bool
    {
        $property = null;
        try {
            $property = $reflection->getProperty($propertyName);
        } catch (ReflectionException) {
            return false;
        }

        $attributes = $property->getAttributes();
        foreach ($attributes as $attr) {
            $attrName = $attr->getName();
            if (SerializedName::class === $attrName
                || Hidden::class === $attrName
                || is_subclass_of($attrName, \Ninja\Granite\Validation\Rules\AbstractRule::class)
                || (class_exists(CarbonDate::class) && CarbonDate::class === $attrName)
            ) {
                return true;
            }

            // Check for validation attributes (any attribute that has asRule())
            if (method_exists($attrName, 'asRule')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private static function hasOverriddenRules(ReflectionClass $reflection): bool
    {
        try {
            $rulesMethod = $reflection->getMethod('rules');
            // If the declaring class is not Granite's HasValidation trait host,
            // then the method has been overridden
            $declaringClass = $rulesMethod->getDeclaringClass()->getName();

            // rules() is defined in HasValidation trait, used by Granite.
            // If declaring class matches the concrete class, it's overridden.
            return $declaringClass === $reflection->getName();
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private static function hasReadonlyParentProperties(ReflectionClass $reflection): bool
    {
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            if ($property->isReadOnly() && $property->getDeclaringClass()->getName() !== $reflection->getName()) {
                return true;
            }
        }

        return false;
    }

    private static function required(): stdClass
    {
        /** @var stdClass|null $sentinel */
        static $sentinel = null;
        $sentinel ??= new stdClass();

        return $sentinel;
    }
}

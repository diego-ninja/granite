<?php
// ABOUTME: Defines RuleExtractor as part of validation rule definition and execution.
// ABOUTME: Owns the RuleExtractor boundary between rule definitions and validation results.

namespace Ninja\Granite\Validation;

use Ninja\Granite\Support\ReflectionCache;
use ReflectionException;

/**
 * Utility to extract validation rules from property attributes.
 */
class RuleExtractor
{
    /** @var array<class-string, array<string, ValidationRule[]>> */
    private static array $rulesCache = [];

    /** @var array<class-string, GraniteValidator> */
    private static array $validatorCache = [];

    /**
     * Extract validation rules from a class's property attributes.
     *
     * @param class-string $class Class name
     * @return array<string, ValidationRule[]> Rules by property name
     * @throws ReflectionException
     */
    public static function extractRules(string $class): array
    {
        if (isset(self::$rulesCache[$class])) {
            return self::$rulesCache[$class];
        }

        $properties = ReflectionCache::getPublicProperties($class);
        $rules = [];

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $propertyRules = [];

            // Get all attributes that can be converted to validation rules
            $attributes = $property->getAttributes();

            foreach ($attributes as $attribute) {
                $attrInstance = $attribute->newInstance();

                // Check if this attribute has a toRule method
                if (method_exists($attrInstance, 'asRule')) {
                    $rule = $attrInstance->asRule();
                    if ($rule instanceof ValidationRule) {
                        $propertyRules[] = $rule;
                    }
                }
            }

            if ( ! empty($propertyRules)) {
                $rules[$propertyName] = $propertyRules;
            }
        }

        self::$rulesCache[$class] = $rules;
        return $rules;
    }

    public static function clearCache(): void
    {
        self::$rulesCache = [];
        self::$validatorCache = [];
    }

    /** @param class-string $class */
    public static function attributeValidator(string $class): ?GraniteValidator
    {
        $rules = self::extractRules($class);
        if ([] === $rules) {
            return null;
        }

        self::$validatorCache[$class] ??= GraniteValidator::fromArray($rules);
        return self::$validatorCache[$class];
    }
}

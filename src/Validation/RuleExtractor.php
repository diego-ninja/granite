<?php

namespace Ninja\Granite\Validation;

use Ninja\Granite\Support\ReflectionCache;
use ReflectionException;

/**
 * Utility to extract validation rules from property attributes.
 */
class RuleExtractor
{
    /**
     * Extract validation rules from a class's property attributes.
     *
     * @param string $class Class name
     * @return array<string, ValidationRule[]> Rules by property name
     * @throws ReflectionException
     */
    public static function extractRules(string $class): array
    {
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
                    $rule = $attrInstance->toRule();
                    if ($rule instanceof ValidationRule) {
                        $propertyRules[] = $rule;
                    }
                }
            }

            if (!empty($propertyRules)) {
                $rules[$propertyName] = $propertyRules;
            }
        }

        return $rules;
    }
}
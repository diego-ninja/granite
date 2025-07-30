<?php

namespace Ninja\Granite\Traits;

use Ninja\Granite\Exceptions\SerializationException;
use Ninja\Granite\Exceptions\ValidationException;
use Ninja\Granite\Validation\GraniteValidator;
use Ninja\Granite\Validation\RuleExtractor;
use Ninja\Granite\Validation\Rules\AbstractRule;
use ReflectionAttribute;
use ReflectionException;
use ReflectionProperty;

/**
 * Trait providing comprehensive validation functionality for Granite objects.
 * Unifies validation logic using RuleExtractor and GraniteValidator.
 */
trait HasValidation
{
    /**
     * Validate the object's properties using the unified validation system.
     * This method validates the current object state.
     *
     * @param array|null $data Optional data context for validation
     * @return bool True if validation passes
     * @throws ReflectionException
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     * @throws SerializationException
     */
    public function validate(?array $data = null): bool
    {
        try {
            $allData = $data ?? $this->array();
            static::validateData($allData, static::class);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Check if the object is valid without throwing exceptions.
     *
     * @param array|null $data Optional data context for validation
     * @return bool True if validation passes, false otherwise
     * @throws ReflectionException
     * @throws SerializationException
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public function isValid(?array $data = null): bool
    {
        return $this->validate($data);
    }

    /**
     * Get validation errors without throwing exceptions.
     *
     * @param array|null $data Optional data context for validation
     * @return array<string, array<string>> Validation errors by property name
     * @throws ReflectionException
     * @throws SerializationException
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public function getValidationErrors(?array $data = null): array
    {
        try {
            $allData = $data ?? $this->array();
            static::validateData($allData, static::class);
            return []; // No errors
        } catch (ValidationException $e) {
            return $e->getErrors();
        }
    }

    /**
     * Validate data and return ValidationException if it fails.
     * Useful for getting the full exception with all details.
     *
     * @param array|null $data Optional data context for validation
     * @return ValidationException|null ValidationException if validation fails, null if passes
     * @throws ReflectionException
     * @throws SerializationException
     * @throws \Ninja\Granite\Exceptions\ReflectionException
     */
    public function getValidationException(?array $data = null): ?ValidationException
    {
        try {
            $allData = $data ?? $this->array();
            static::validateData($allData, static::class);
            return null; // No errors
        } catch (ValidationException $e) {
            return $e;
        }
    }
    /**
     * Validate data using the unified validation system.
     * Uses both attribute-based rules and method-based rules.
     *
     * @param array $data Data to validate
     * @param string $objectName Object name for error messages
     * @throws ValidationException If validation fails
     * @throws ReflectionException
     */
    protected static function validateData(array $data, string $objectName): void
    {
        // Get rules from both method and attributes
        $methodRules = static::rules();
        $attributeRules = RuleExtractor::extractRules(static::class);

        // Merge rules, preferring method rules if defined for the same property
        $rules = $attributeRules;
        foreach ($methodRules as $property => $propertyRules) {
            $rules[$property] = $propertyRules;
        }

        // Only validate if we have rules
        if ( ! empty($rules)) {
            GraniteValidator::fromArray($rules)->validate($data, $objectName);
        }
    }

    /**
     * Check if there are any validation rules defined for this class.
     *
     * @return bool True if validation rules exist
     * @throws ReflectionException
     */
    protected static function hasValidationRules(): bool
    {
        $methodRules = static::rules();
        $attributeRules = RuleExtractor::extractRules(static::class);

        return ! empty($methodRules) || ! empty($attributeRules);
    }

    /**
     * Get validation rules for this object.
     * Override this method in child classes to define validation rules.
     *
     * This method provides programmatic validation rules that complement
     * attribute-based validation rules defined on properties.
     *
     * @return array<string, array> Validation rules by property name
     */
    protected static function rules(): array
    {
        return [];
    }

    /**
     * Validate a single property using the legacy attribute-based validation.
     * This method is kept for backward compatibility but the new unified
     * validation system should be preferred.
     *
     * @param ReflectionProperty $property Property to validate
     * @param mixed $value Property value
     * @param array $allData All object data for context
     * @return array<string> Validation error messages
     * @deprecated Use the unified validation system instead
     */
    protected function validateProperty(ReflectionProperty $property, mixed $value, array $allData): array
    {
        $errors = [];
        $validationAttributes = $property->getAttributes(AbstractRule::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($validationAttributes as $attribute) {
            /** @var AbstractRule $rule */
            $rule = $attribute->newInstance();

            if ( ! $rule->validate($value, $allData)) {
                $message = $rule->message($property->getName());
                $errors[] = $message;
            }
        }

        return $errors;
    }
}

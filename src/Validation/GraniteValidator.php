<?php

namespace Ninja\Granite\Validation;

use InvalidArgumentException;
use Ninja\Granite\Exceptions\ValidationException;

final class GraniteValidator
{
    /**
     * Collections of validation rules.
     *
     * @var RuleCollection[]
     */
    private array $collections = [];

    /**
     * Constructor.
     *
     * @param RuleCollection|RuleCollection[] $collections Rule collections (optional)
     */
    public function __construct(RuleCollection|array $collections = [])
    {
        if ($collections instanceof RuleCollection) {
            $this->collections[$collections->getProperty()] = $collections;
        } elseif (is_array($collections)) {
            foreach ($collections as $collection) {
                if ($collection instanceof RuleCollection) {
                    $this->collections[$collection->getProperty()] = $collection;
                }
            }
        }
    }

    /**
     * Add a rule collection to the validator.
     *
     * @param RuleCollection $collection The rule collection
     * @return $this For method chaining
     */
    public function addRules(RuleCollection $collection): self
    {
        $this->collections[$collection->getProperty()] = $collection;
        return $this;
    }

    /**
     * Add a single rule for a property.
     *
     * @param string $property The property name
     * @param ValidationRule $rule The validation rule
     * @return $this For method chaining
     */
    public function addRule(string $property, ValidationRule $rule): self
    {
        if (!isset($this->collections[$property])) {
            $this->collections[$property] = new RuleCollection($property);
        }

        $this->collections[$property]->add($rule);
        return $this;
    }

    /**
     * Create a rule collection for a property.
     *
     * @param string $property The property name
     * @return RuleCollection New rule collection
     */
    public function forProperty(string $property): RuleCollection
    {
        if (!isset($this->collections[$property])) {
            $this->collections[$property] = new RuleCollection($property);
        }

        return $this->collections[$property];
    }

    /**
     * Validate data against all rule collections.
     *
     * @param array $data Data to validate
     * @param string $objectName Object name for error messages
     * @throws ValidationException If validation fails
     */
    public function validate(array $data, string $objectName = 'Object'): void
    {
        $errors = [];

        foreach ($this->collections as $property => $collection) {
            // Check if property exists in data
            if (!array_key_exists($property, $data)) {
                // Look for required rule
                foreach ($collection->getRules() as $rule) {
                    if ($rule instanceof Rules\Required) {
                        $errors[$property][] = $rule->message($property);
                        break;
                    }
                }
                continue;
            }

            $value = $data[$property];
            $propertyErrors = $collection->validate($value, $data);

            if (!empty($propertyErrors)) {
                $errors[$property] = $propertyErrors;
            }
        }

        // Throw exception with validation errors if any
        if (!empty($errors)) {
            throw new ValidationException($objectName, $errors);
        }
    }

    /**
     * Create a validator from an array of rule definitions.
     * Supports both array format and string format rules.
     *
     * @param array $rulesArray Array of rule definitions
     * @return self New validator instance
     */
    public static function fromArray(array $rulesArray): self
    {
        $validator = new self();

        foreach ($rulesArray as $property => $propertyRules) {
            $collection = new RuleCollection($property);

            // Handle string format rules (e.g. 'required|string|min:3')
            if (is_string($propertyRules)) {
                $rules = RuleParser::parse($propertyRules);
                foreach ($rules as $rule) {
                    $collection->add($rule);
                }
            }
            // Handle array format rules
            elseif (is_array($propertyRules)) {
                foreach ($propertyRules as $ruleDefinition) {
                    // Support for string format within arrays (e.g. ['required|string', ...])
                    if (is_string($ruleDefinition)) {
                        $rules = RuleParser::parse($ruleDefinition);
                        foreach ($rules as $rule) {
                            $collection->add($rule);
                        }
                    }
                    // Support for traditional array format
                    elseif (is_array($ruleDefinition)) {
                        $rule = self::createRuleFromDefinition($ruleDefinition);
                        if ($rule !== null) {
                            $collection->add($rule);
                        }
                    }
                    elseif ($ruleDefinition instanceof ValidationRule) {
                        $collection->add($ruleDefinition);
                    } else {
                        throw new InvalidArgumentException("Invalid rule definition for property '$property'");
                    }
                }
            }

            if (!empty($collection->getRules())) {
                $validator->addRules($collection);
            }
        }

        return $validator;
    }
    /**
     * Create a rule instance from a rule definition array.
     *
     * @param array $definition Rule definition
     * @return ValidationRule|null Rule instance or null if invalid
     */
    private static function createRuleFromDefinition(array $definition): ?ValidationRule
    {
        $type = $definition['type'] ?? '';
        $message = $definition['message'] ?? null;

        $rule = match ($type) {
            'required' => new Rules\Required(),
            'string' => new Rules\StringType(),
            'int', 'integer' => new Rules\IntegerType(),
            'float', 'number' => new Rules\NumberType(),
            'bool', 'boolean' => new Rules\BooleanType(),
            'array' => new Rules\ArrayType(),
            'min' => isset($definition['value']) ? new Rules\Min($definition['value']) : null,
            'max' => isset($definition['value']) ? new Rules\Max($definition['value']) : null,
            'in' => isset($definition['values']) ? new Rules\In($definition['values']) : null,
            'regex' => isset($definition['pattern']) ? new Rules\Regex($definition['pattern']) : null,
            'email' => new Rules\Email(),
            'url' => new Rules\Url(),
            'ip' => new Rules\IpAddress(),
            'callback' => isset($definition['callback']) ? new Rules\Callback($definition['callback']) : null,
            'when' => isset($definition['condition']) && isset($definition['rule'])
                ? new Rules\When($definition['condition'], $definition['rule']) : null,
            'each' => isset($definition['rules'])
                ? new Rules\Each($definition['rules']) : null,
            default => null,
        };

        if ($rule !== null && $message !== null) {
            $rule->withMessage($message);
        }

        return $rule;
    }
}
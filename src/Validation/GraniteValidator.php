<?php

namespace Ninja\Granite\Validation;

use InvalidArgumentException;
use Ninja\Granite\Exceptions\ValidationException;

final class GraniteValidator
{
    /**
     * Collections of validation rules.
     *
     * @var array<string, RuleCollection>
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
                $this->collections[$collection->getProperty()] = $collection;
            }
        }
    }

    /**
     * Create a validator from an array of rule definitions.
     * Supports both array format and string format rules.
     *
     * @param array<array-key, mixed> $rulesArray Array of rule definitions
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
                foreach ($propertyRules as $position => $ruleDefinition) {
                    // Support for string format within arrays (e.g. ['required|string', ...])
                    if (is_string($ruleDefinition)) {
                        $rules = RuleParser::parse($ruleDefinition);
                        foreach ($rules as $rule) {
                            $collection->add($rule);
                        }
                    }
                    // Support for traditional array format
                    elseif (is_array($ruleDefinition)) {
                        $collection->add(self::createRuleFromDefinition($ruleDefinition, (string) $property, $position));
                    } elseif ($ruleDefinition instanceof ValidationRule) {
                        $collection->add($ruleDefinition);
                    } else {
                        throw new InvalidArgumentException("Invalid rule definition for property '{$property}'");
                    }
                }
            }

            if ( ! empty($collection->getRules())) {
                $validator->addRules($collection);
            }
        }

        return $validator;
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
        if ( ! isset($this->collections[$property])) {
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
        if ( ! isset($this->collections[$property])) {
            $this->collections[$property] = new RuleCollection($property);
        }

        return $this->collections[$property];
    }

    /**
     * Validate data against all rule collections.
     *
     * @param array<array-key, mixed> $data Data to validate
     * @param string $objectName Object name for error messages
     * @throws ValidationException If validation fails
     */
    public function validate(array $data, string $objectName = 'Object'): void
    {
        $errors = self::emptyErrors();

        foreach ($this->collections as $property => $collection) {
            // Check if property exists in data
            if ( ! array_key_exists($property, $data)) {
                // Look for required rule
                foreach ($collection->getRules() as $rule) {
                    if ($rule instanceof Rules\Required) {
                        $errors[$property] ??= [];
                        $errors[$property][] = $rule->message($property);
                        break;
                    }
                }
                continue;
            }

            $value = $data[$property];
            $propertyErrors = $collection->validate($value, $data);

            if ( ! empty($propertyErrors)) {
                $errors[$property] = $propertyErrors;
            }
        }

        // Throw exception with validation errors if any
        if ( ! empty($errors)) {
            throw new ValidationException($objectName, $errors);
        }
    }

    /** @return array<string, array<int, string>> */
    private static function emptyErrors(): array
    {
        return [];
    }
    /**
     * Create a rule instance from a rule definition array.
     *
     * @param array<array-key, mixed> $definition Rule definition
     * @return ValidationRule Rule instance
     */
    private static function createRuleFromDefinition(array $definition, string $property, int|string $position): ValidationRule
    {
        $type = $definition['type'] ?? null;
        $message = $definition['message'] ?? null;

        if ( ! is_string($type) || '' === $type) {
            throw self::invalidRuleDefinition($property, $position, 'missing rule type');
        }

        $rule = match ($type) {
            'required' => new Rules\Required(),
            'string' => new Rules\StringType(),
            'int', 'integer' => new Rules\IntegerType(),
            'float', 'number' => new Rules\NumberType(),
            'bool', 'boolean' => new Rules\BooleanType(),
            'array' => new Rules\ArrayType(),
            'min', 'max' => self::createBoundaryRule($type, $definition, $property, $position),
            'in' => self::createInRule($definition, $property, $position),
            'regex' => self::createRegexRule($definition, $property, $position),
            'email' => new Rules\Email(),
            'url' => new Rules\Url(),
            'ip' => new Rules\IpAddress(),
            'callback' => isset($definition['callback']) && is_callable($definition['callback']) ? new Rules\Callback($definition['callback']) : null,
            'when' => isset($definition['condition']) && isset($definition['rule']) && is_callable($definition['condition']) && $definition['rule'] instanceof ValidationRule
                ? new Rules\When($definition['condition'], $definition['rule']) : null,
            'each' => isset($definition['rules']) && ($definition['rules'] instanceof ValidationRule)
                ? new Rules\Each($definition['rules']) : null,
            default => throw self::invalidRuleDefinition($property, $position, sprintf('unknown rule "%s"', $type)),
        };

        if ( ! $rule instanceof ValidationRule) {
            throw self::invalidRuleDefinition($property, $position, sprintf('malformed rule "%s"', $type));
        }

        if (null !== $message && ! is_string($message)) {
            throw self::invalidRuleDefinition($property, $position, 'message must be a string');
        }

        if (null !== $message && $rule instanceof Rules\AbstractRule) {
            $rule->withMessage($message);
        }

        return $rule;
    }

    /** @param array<string, mixed> $definition */
    /** @param array<array-key, mixed> $definition */
    private static function createBoundaryRule(string $type, array $definition, string $property, int|string $position): ValidationRule
    {
        $value = $definition['value'] ?? null;
        if ( ! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw self::invalidRuleDefinition($property, $position, sprintf('rule "%s" requires a numeric value', $type));
        }

        if (is_string($value) && ('' === $value || $value !== trim($value) || ! is_numeric($value))) {
            throw self::invalidRuleDefinition($property, $position, sprintf('rule "%s" requires a numeric value', $type));
        }

        $normalized = is_string($value) ? strtolower($value) : '';
        $parsed = is_float($value) || str_contains((string) $value, '.') || str_contains($normalized, 'e')
            ? (float) $value
            : (int) $value;

        return 'min' === $type ? new Rules\Min($parsed) : new Rules\Max($parsed);
    }

    /** @param array<string, mixed> $definition */
    /** @param array<array-key, mixed> $definition */
    private static function createInRule(array $definition, string $property, int|string $position): ValidationRule
    {
        $values = $definition['values'] ?? null;
        if ( ! is_array($values) || [] === $values || in_array('', $values, true)) {
            throw self::invalidRuleDefinition($property, $position, 'rule "in" requires non-empty values');
        }

        return new Rules\In($values);
    }

    /** @param array<string, mixed> $definition */
    /** @param array<array-key, mixed> $definition */
    private static function createRegexRule(array $definition, string $property, int|string $position): ValidationRule
    {
        $pattern = $definition['pattern'] ?? null;
        if ( ! is_string($pattern) || '' === $pattern) {
            throw self::invalidRuleDefinition($property, $position, 'rule "regex" requires a non-empty pattern');
        }

        return new Rules\Regex($pattern);
    }

    private static function invalidRuleDefinition(string $property, int|string $position, string $reason): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Invalid rule definition for property "%s" at position %s: %s',
            $property,
            $position,
            $reason,
        ));
    }
}

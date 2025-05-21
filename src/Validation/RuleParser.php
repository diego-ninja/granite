<?php

namespace Ninja\Granite\Validation;

use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\StringType;
use Ninja\Granite\Validation\Rules\IntegerType;
use Ninja\Granite\Validation\Rules\NumberType;
use Ninja\Granite\Validation\Rules\BooleanType;
use Ninja\Granite\Validation\Rules\ArrayType;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\Max;
use Ninja\Granite\Validation\Rules\In;
use Ninja\Granite\Validation\Rules\Regex;
use Ninja\Granite\Validation\Rules\Email;
use Ninja\Granite\Validation\Rules\Url;
use Ninja\Granite\Validation\Rules\IpAddress;

/**
 * Parser for string-based validation rules.
 */
class RuleParser
{
    /**
     * Parse a validation rule string into rule objects.
     *
     * @param string $ruleString Rule string (e.g. 'required|string|min:3')
     * @return ValidationRule[] Array of validation rule objects
     */
    public static function parse(string $ruleString): array
    {
        $rules = [];
        $ruleParts = explode('|', $ruleString);

        foreach ($ruleParts as $rulePart) {
            // Skip empty parts
            if (empty($rulePart)) {
                continue;
            }

            // Check if rule has parameters
            if (str_contains($rulePart, ':')) {
                [$ruleName, $parameters] = explode(':', $rulePart, 2);
                $rule = self::createRuleWithParameters($ruleName, $parameters);
            } else {
                $rule = self::createSimpleRule($rulePart);
            }

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Create a simple validation rule without parameters.
     *
     * @param string $ruleName Rule name
     * @return ValidationRule|null Rule object or null if invalid
     */
    private static function createSimpleRule(string $ruleName): ?ValidationRule
    {
        return match ($ruleName) {
            'required' => new Required(),
            'string' => new StringType(),
            'int', 'integer' => new IntegerType(),
            'float', 'number' => new NumberType(),
            'bool', 'boolean' => new BooleanType(),
            'array' => new ArrayType(),
            'email' => new Email(),
            'url' => new Url(),
            'ip' => new IpAddress(),
            default => null,
        };
    }

    /**
     * Create a validation rule with parameters.
     *
     * @param string $ruleName Rule name
     * @param string $parameters Rule parameters
     * @return ValidationRule|null Rule object or null if invalid
     */
    private static function createRuleWithParameters(string $ruleName, string $parameters): ?ValidationRule
    {
        // Split parameters by comma if multiple parameters
        $params = str_contains($parameters, ',') ? explode(',', $parameters) : [$parameters];

        return match ($ruleName) {
            'min' => isset($params[0]) ? new Min((int) $params[0]) : null,
            'max' => isset($params[0]) ? new Max((int) $params[0]) : null,
            'in' => !empty($params) ? new In($params) : null,
            'regex' => isset($params[0]) ? new Regex($params[0]) : null,
            default => null,
        };
    }
}
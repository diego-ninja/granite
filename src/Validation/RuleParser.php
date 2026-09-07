<?php
// ABOUTME: Defines RuleParser as part of validation rule definition and execution.
// ABOUTME: Owns the RuleParser boundary between rule definitions and validation results.

namespace Ninja\Granite\Validation;

use InvalidArgumentException;
use Ninja\Granite\Validation\Rules\ArrayType;
use Ninja\Granite\Validation\Rules\BooleanType;
use Ninja\Granite\Validation\Rules\Email;
use Ninja\Granite\Validation\Rules\In;
use Ninja\Granite\Validation\Rules\IntegerType;
use Ninja\Granite\Validation\Rules\IpAddress;
use Ninja\Granite\Validation\Rules\Max;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\NumberType;
use Ninja\Granite\Validation\Rules\Regex;
use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\StringType;
use Ninja\Granite\Validation\Rules\Url;

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
        if ('' === $ruleString) {
            return [];
        }

        $rules = [];
        $ruleParts = self::splitRules($ruleString);

        foreach ($ruleParts as $position => $rulePart) {
            if ('' === $rulePart) {
                continue;
            }

            if (str_contains($rulePart, ':')) {
                [$ruleName, $parameters] = explode(':', $rulePart, 2);
                $rule = self::createRuleWithParameters($ruleName, $parameters, $position + 1);
            } else {
                $rule = self::createSimpleRule($rulePart, $position + 1);
            }

            $rules[] = $rule;
        }

        if ([] === $rules) {
            throw new InvalidArgumentException('No valid validation rules found in input');
        }

        return $rules;
    }

    /** @return list<string> */
    private static function splitRules(string $ruleString): array
    {
        $parts = [];
        $current = '';
        $regexOpeningDelimiter = null;
        $regexClosingDelimiter = null;
        $regexDelimiterDepth = 0;
        $escaped = false;
        $length = strlen($ruleString);

        for ($index = 0; $index < $length; $index++) {
            $character = $ruleString[$index];

            if (null !== $regexClosingDelimiter) {
                $current .= $character;
                if ($escaped) {
                    $escaped = false;
                } elseif ('\\' === $character) {
                    $escaped = true;
                } elseif ($regexOpeningDelimiter !== $regexClosingDelimiter
                    && $character === $regexOpeningDelimiter) {
                    $regexDelimiterDepth++;
                } elseif ($character === $regexClosingDelimiter) {
                    $regexDelimiterDepth--;
                    if (0 === $regexDelimiterDepth) {
                        $regexOpeningDelimiter = null;
                        $regexClosingDelimiter = null;
                    }
                }
                continue;
            }

            if ('|' === $character) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $character;
            if (7 === strlen($current) && str_starts_with($current, 'regex:')) {
                $regexOpeningDelimiter = $current[6];
                $regexClosingDelimiter = match ($regexOpeningDelimiter) {
                    '(' => ')',
                    '[' => ']',
                    '{' => '}',
                    '<' => '>',
                    default => $regexOpeningDelimiter,
                };
                $regexDelimiterDepth = 1;
            }
        }

        $parts[] = $current;
        return $parts;
    }

    /**
     * Create a simple validation rule without parameters.
     *
     * @param string $ruleName Rule name
     * @return ValidationRule Rule object
     */
    private static function createSimpleRule(string $ruleName, int $position): ValidationRule
    {
        $rule = match ($ruleName) {
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

        if (null === $rule) {
            throw self::invalidRule($ruleName, $position, 'unknown rule');
        }

        return $rule;
    }

    /**
     * Create a validation rule with parameters.
     *
     * @param string $ruleName Rule name
     * @param string $parameters Rule parameters
     * @return ValidationRule Rule object
     */
    private static function createRuleWithParameters(string $ruleName, string $parameters, int $position): ValidationRule
    {
        return match ($ruleName) {
            'min' => new Min(self::parseBoundary($ruleName, $parameters, $position)),
            'max' => new Max(self::parseBoundary($ruleName, $parameters, $position)),
            'in' => new In(self::parseInValues($parameters, $position)),
            'regex' => self::createRegex($parameters, $position),
            default => throw self::invalidRule($ruleName, $position, 'unknown rule'),
        };
    }

    private static function parseBoundary(string $ruleName, string $parameter, int $position): int|float
    {
        if ('' === $parameter || str_contains($parameter, ',') || $parameter !== trim($parameter) || ! is_numeric($parameter)) {
            throw self::invalidRule($ruleName, $position, 'requires one numeric parameter');
        }

        $normalized = strtolower($parameter);

        return str_contains($parameter, '.') || str_contains($normalized, 'e')
            ? (float) $parameter
            : (int) $parameter;
    }

    /**
     * @return list<string>
     */
    private static function parseInValues(string $parameters, int $position): array
    {
        $values = explode(',', $parameters);
        if (in_array('', $values, true)) {
            throw self::invalidRule('in', $position, 'requires one or more non-empty values');
        }

        return $values;
    }

    private static function createRegex(string $pattern, int $position): Regex
    {
        if ('' === $pattern) {
            throw self::invalidRule('regex', $position, 'requires a non-empty pattern');
        }

        return new Regex($pattern);
    }

    private static function invalidRule(string $ruleName, int $position, string $reason): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Invalid validation rule "%s" at position %d: %s',
            $ruleName,
            $position,
            $reason,
        ));
    }
}

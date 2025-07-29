<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Support\StringHelper;

/**
 * Implementation of PascalCase naming convention (e.g., FirstName, LastName, EmailAddress).
 */
class PascalCaseConvention extends AbstractNamingConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'PascalCase';
    }

    public function matches(string $name): bool
    {
        // PascalCase: first character uppercase, no spaces or underscores,
        // contains at least one lowercase character
        return 1 === preg_match('/^[A-Z][a-z0-9]+([A-Z][a-z0-9]+)*$/', $name);
    }

    public function normalize(string $name): string
    {
        // Convert to words with spaces by inserting a space before each capital letter
        // Example: "FirstName" -> "First Name"
        $result = preg_replace('/(?<!^)([A-Z])/', ' $1', $name);
        return mb_strtolower($result ?? $name);
    }

    public function denormalize(string $normalized): string
    {
        // Convert space-separated words to PascalCase
        // Example: "first name" -> "FirstName"
        $words = explode(' ', $normalized);
        $result = '';

        foreach ($words as $word) {
            if ('' !== StringHelper::mbTrim($word)) {
                $result .= ucfirst(mb_strtolower(StringHelper::mbTrim($word)));
            }
        }

        return $result;
    }
}

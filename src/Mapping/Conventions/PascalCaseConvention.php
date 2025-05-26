<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

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
        return preg_match('/^[A-Z][a-z0-9]+([A-Z][a-z0-9]+)*$/', $name) === 1;
    }
    
    public function normalize(string $name): string
    {
        // Convert to words with spaces by inserting a space before each capital letter
        // Example: "FirstName" -> "First Name"
        $result = preg_replace('/(?<!^)([A-Z])/', ' $1', $name);
        return strtolower($result);
    }
    
    public function denormalize(string $normalized): string
    {
        // Convert space-separated words to PascalCase
        // Example: "first name" -> "FirstName"
        $words = explode(' ', $normalized);
        $result = '';
        
        foreach ($words as $word) {
            if (trim($word) !== '') {
                $result .= ucfirst(strtolower(trim($word)));
            }
        }
        
        return $result;
    }
}

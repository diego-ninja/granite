<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

/**
 * Implementation of camelCase naming convention (e.g., firstName, lastName, emailAddress).
 */
class CamelCaseConvention extends AbstractNamingConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'camelCase';
    }
    
    public function matches(string $name): bool
    {
        // camelCase: first character lowercase, no spaces or underscores,
        // contains at least one uppercase character
        return preg_match('/^[a-z][a-z0-9]*([A-Z][a-z0-9]*)+$/', $name) === 1;
    }
    
    public function normalize(string $name): string
    {
        // Convert to words with spaces by inserting a space before each capital letter
        // Example: "firstName" -> "first name"
        $result = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);
        return strtolower($result);
    }
    
    public function denormalize(string $normalized): string
    {
        // Convert space-separated words to camelCase
        // Example: "first name" -> "firstName"
        $words = explode(' ', $normalized);
        $result = strtolower($words[0]);
        
        for ($i = 1; $i < count($words); $i++) {
            if (trim($words[$i]) !== '') {
                $result .= ucfirst(strtolower(trim($words[$i])));
            }
        }
        
        return $result;
    }
}

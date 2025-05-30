<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

/**
 * Implementation of kebab-case naming convention (e.g., first-name, last-name, email-address).
 */
class KebabCaseConvention extends AbstractNamingConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'kebab-case';
    }
    
    public function matches(string $name): bool
    {
        // Kebab case: all lowercase with hyphens
        return preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $name) === 1;
    }
    
    public function normalize(string $name): string
    {
        // Convert kebab-case to words with spaces
        // Example: "first-name" -> "first name"
        return str_replace('-', ' ', $name);
    }
    
    public function denormalize(string $normalized): string
    {
        // Convert space-separated words to kebab-case
        // Example: "first name" -> "first-name"
        $words = explode(' ', $normalized);
        $result = '';
        
        foreach ($words as $i => $word) {
            if (trim($word) !== '') {
                if ($i > 0) {
                    $result .= '-';
                }
                $result .= strtolower(trim($word));
            }
        }
        
        return $result;
    }
}

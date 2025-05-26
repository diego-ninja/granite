<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

/**
 * Implementation of snake_case naming convention (e.g., first_name, last_name, email_address).
 */
class SnakeCaseConvention extends AbstractNamingConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'snake_case';
    }
    
    public function matches(string $name): bool
    {
        // Snake case: all lowercase with underscores
        return preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $name) === 1;
    }
    
    public function normalize(string $name): string
    {
        // Convert snake_case to words with spaces
        // Example: "first_name" -> "first name"
        return str_replace('_', ' ', $name);
    }
    
    public function denormalize(string $normalized): string
    {
        // Convert space-separated words to snake_case
        // Example: "first name" -> "first_name"
        $words = explode(' ', $normalized);
        $result = '';
        
        foreach ($words as $i => $word) {
            if (trim($word) !== '') {
                if ($i > 0) {
                    $result .= '_';
                }
                $result .= strtolower(trim($word));
            }
        }
        
        return $result;
    }
}

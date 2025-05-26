<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

/**
 * Convention for handling Hungarian notation (type prefixes like 'str', 'int', 'bool', etc.)
 */
class HungarianNotationConvention extends AbstractNamingConvention implements NamingConvention
{
    /**
     * @var array<string, string> Type prefix mapping
     */
    private array $typePrefixes = [
        'str' => 'string',
        'int' => 'integer',
        'bool' => 'boolean',
        'flt' => 'float',
        'arr' => 'array',
        'obj' => 'object',
        'n' => 'number',
        'b' => 'boolean',
        's' => 'string',
        'a' => 'array',
        'i' => 'integer',
        'f' => 'float',
        'o' => 'object',
    ];
    
    public function getName(): string
    {
        return 'hungarian';
    }
    
    public function matches(string $name): bool
    {
        foreach ($this->typePrefixes as $prefix => $type) {
            if (preg_match('/^' . $prefix . '[A-Z]/', $name)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function normalize(string $name): string
    {
        foreach ($this->typePrefixes as $prefix => $type) {
            if (preg_match('/^' . $prefix . '([A-Z].*)$/', $name, $matches)) {
                // Convert first letter to lowercase
                $withoutPrefix = lcfirst($matches[1]);
                
                // Use camelCase normalization
                $camelConvention = new CamelCaseConvention();
                return $camelConvention->normalize($withoutPrefix);
            }
        }
        
        return $name;
    }
    
    public function denormalize(string $normalized): string
    {
        // By default, we use 'str' prefix for denormalization
        $camelConvention = new CamelCaseConvention();
        $camelCase = $camelConvention->denormalize($normalized);
        
        return 'str' . ucfirst($camelCase);
    }
    
    /**
     * Detects which convention a property name uses.
     */
    private function detectConvention(string $name): ?NamingConvention
    {
        $conventions = [
            new CamelCaseConvention(),
            new PascalCaseConvention(),
            new SnakeCaseConvention(),
            new KebabCaseConvention()
        ];
        
        foreach ($conventions as $convention) {
            if ($convention->matches($name)) {
                return $convention;
            }
        }
        
        return null;
    }
}
